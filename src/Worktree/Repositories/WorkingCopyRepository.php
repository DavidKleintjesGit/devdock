<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Src\Project\Contracts\ProjectPhpRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Exceptions\NotAWorkingCopyException;
use Src\Worktree\Objects\SiteName;

class WorkingCopyRepository implements WorkingCopyRepositoryContract
{
	/**
	 * Herd keeps its settings here, and the folder next to that file holds
	 * every site it serves without anything being parked.
	 */
	private const CLAUDE_CONFIG = '/.claude.json';

	private const HERD_CONFIG = '/.config/herd/config/valet/config.json';

	public function prepare(string $repositoryPath, string $path, SiteName $siteName): ?string
	{
		$this->ensureWorkingCopy($path);
		$this->exclude($repositoryPath);
		$this->trust($repositoryPath);
		$this->borrow($repositoryPath, $path);
		$this->carryOver($repositoryPath, $path);

		$host = $this->publish($path, $siteName);

		if ($host !== null) {
			$this->pointAppUrl($path, $host);
		}

		return $host;
	}

	public function release(string $path, ?string $site): void
	{
		$this->ensureWorkingCopy($path);

		foreach (array_keys((array) config('devdock.worktree.share')) as $folder) {
			$this->returnBorrowed($path, $folder);
		}

		if ($site !== null) {
			$sites = $this->sitesPath();

			if ($sites !== null) {
				$this->drop($sites . '/' . $this->nameOf($site));
			}
		}
	}

	public function stopProcesses(string $path): int
	{
		$this->ensureWorkingCopy($path);

		if (PHP_OS_FAMILY !== 'Windows') {
			return Process::run(['pkill', '-f', '--', rtrim($path, '/') . '/'])->successful() ? 1 : 0;
		}

		// command lines are compared with forward slashes, and the folder must end there, so .worktrees/shop does not also hit .worktrees/shop-billing
		$script = <<<'POWERSHELL'
			$folder = [regex]::Escape($env:DEVDOCK_STOP_FOLDER) + '([/" '']|$)'
			$stopped = 0
			Get-CimInstance Win32_Process |
				Where-Object { $_.ProcessId -ne $PID -and $_.CommandLine -and $_.CommandLine.Replace([char]92, [char]47).ToLower() -match $folder } |
				ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue; $stopped++ }
			$stopped
			POWERSHELL;

		$result = Process::env(['DEVDOCK_STOP_FOLDER' => strtolower(rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/'))])
			->timeout(60)
			->run(['powershell', '-NoProfile', '-NonInteractive', '-Command', $script]);

		return $result->successful() ? (int) trim($result->output()) : 0;
	}

	public function returnBorrowed(string $path, string $folder): void
	{
		$this->ensureWorkingCopy($path);
		$this->removeWithoutFollowingLinks($path . '/' . $folder);
	}

	/**
	 * Refuses the checkout of a project itself: there .git is a folder, where
	 * a working copy has a file pointing at it. Lending and taking back folders
	 * there would replace or remove the project's own vendor and node_modules.
	 */
	private function ensureWorkingCopy(string $path): void
	{
		if (is_dir($path . '/.git')) {
			throw NotAWorkingCopyException::at($path);
		}
	}

	/**
	 * Removes a folder that may hold links into the checkout. Every link is
	 * taken away as a link and never entered, so nothing in the checkout is
	 * touched; the files of the copy itself, hard links included, go with it.
	 */
	private function removeWithoutFollowingLinks(string $path): void
	{
		if ($this->isLink($path)) {
			$this->drop($path);

			return;
		}

		if (! is_dir($path)) {
			if (is_file($path)) {
				@chmod($path, 0666);
				@unlink($path);
			}

			return;
		}

		foreach (@scandir($path) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->removeWithoutFollowingLinks($path . '/' . $entry);
			}
		}

		@rmdir($path);
		clearstatcache(true);
	}

	public function isPublished(string $site): ?bool
	{
		$sites = $this->sitesPath();

		return $sites === null || ! is_dir($sites) ? null : file_exists($sites . '/' . $this->nameOf($site));
	}

	/**
	 * Keeps the working copies out of the checkout they stand in. This goes in
	 * git's own exclude file and not in .gitignore, which is shared with
	 * everyone else on the project.
	 */
	public function getCarried(string $path): array
	{
		return array_values(array_filter(
			(array) config('devdock.worktree.copy'),
			fn (string $entry) => file_exists($path . '/' . $entry),
		));
	}

	public function carryOverAgain(string $repositoryPath, string $path): void
	{
		foreach ((array) config('devdock.worktree.copy') as $entry) {
			$from = $repositoryPath . '/' . $entry;
			$to = $path . '/' . $entry;

			if (! file_exists($from)) {
				continue;
			}

			File::ensureDirectoryExists(dirname($to));

			// the .env keeps the database and address this copy was given
			if ($entry === '.env' && is_file($to)) {
				continue;
			}

			is_dir($from) ? File::copyDirectory($from, $to) : File::copy($from, $to);
		}
	}

	public function getPackages(string $repositoryPath, string $path): array
	{
		$packages = [];

		foreach ((array) config('devdock.worktree.share') as $folder => $lock) {
			if (! is_dir($repositoryPath . '/' . $folder)) {
				continue;
			}

			$packages[] = [
				'folder' => $folder,
				'lock' => $lock,
				'isBorrowed' => $this->isLink($path . '/' . $folder) || $this->holdsLinks($path . '/' . $folder),
				'isSameLock' => $this->sameFile($repositoryPath . '/' . $lock, $path . '/' . $lock),
			];
		}

		return $packages;
	}

	/**
	 * Whether a folder is a link or, on Windows, a junction.
	 */
	private function isLink(string $path): bool
	{
		return is_link($path) || $this->isJunction($path);
	}

	/**
	 * Whether two paths end up in the same folder on disk.
	 */
	private function isSameFolder(string $left, string $right): bool
	{
		$realLeft = realpath($left);
		$realRight = realpath($right);

		return $realLeft !== false && $realRight !== false && strcasecmp($realLeft, $realRight) === 0;
	}

	/**
	 * Whether a real folder holds borrowed folders, as a vendor borrowed
	 * package by package does.
	 */
	private function holdsLinks(string $path): bool
	{
		return $this->linkedFolders($path) !== [];
	}

	/**
	 * The borrowed folders directly inside a folder. Read with scandir: a
	 * junction does not always pass as a folder in php.
	 *
	 * @return array<int, string>
	 */
	private function linkedFolders(string $path): array
	{
		return collect(@scandir($path) ?: [])
			->reject(fn (string $entry) => $entry === '.' || $entry === '..')
			->map(fn (string $entry) => $path . '/' . $entry)
			->filter(fn (string $entry) => $this->isLink($entry))
			->values()
			->all();
	}

	/**
	 * On Windows a borrowed folder is a junction, which php does not call a
	 * link.
	 */
	private function isJunction(string $path): bool
	{
		// no is_dir here: after is_link, php's stat cache holds a junction as mode 0
		$real = realpath($path);

		return $real !== false && strcasecmp(str_replace('\\', '/', $real), str_replace('\\', '/', $path)) !== 0;
	}

	private function exclude(string $repositoryPath): void
	{
		$file = $repositoryPath . '/.git/info/exclude';

		if (! is_file($file)) {
			return;
		}

		$lines = [
			trim((string) config('devdock.worktree.folder'), '/') . '/',
			...(array) config('devdock.worktree.own_files'),
		];
		$standing = (string) file_get_contents($file);

		// the file is written with the line endings of this machine, so the
		// carriage return has to be allowed for or a line is added twice
		$missing = array_filter($lines, fn (string $line): bool => preg_match('/^' . preg_quote($line, '/') . '\r?$/m', $standing) !== 1);

		if ($missing !== []) {
			file_put_contents($file, rtrim($standing, "\r\n") . PHP_EOL . implode(PHP_EOL, $missing) . PHP_EOL);
		}
	}

	/**
	 * Points the heavy folders at the ones the checkout already holds. Node
	 * walks up by itself, so a project without a lock file still finds its
	 * packages one folder higher.
	 */
	private function borrow(string $repositoryPath, string $path): void
	{
		foreach ((array) config('devdock.worktree.share') as $folder => $lock) {
			$target = $repositoryPath . '/' . $folder;
			$link = $path . '/' . $folder;

			if (! is_dir($target) || file_exists($link)) {
				continue;
			}

			if (! $this->sameFile($repositoryPath . '/' . $lock, $path . '/' . $lock)) {
				continue;
			}

			$folder === 'vendor' ? $this->borrowVendor($target, $link) : $this->link($target, $link);
		}
	}

	/**
	 * Borrows composer's packages one by one and copies its own files in.
	 * Composer finds the project's classes from the folder its autoload stands
	 * in; through one link for the whole vendor folder that is the checkout,
	 * and the tests here would run the checkout's code.
	 */
	private function borrowVendor(string $target, string $link): void
	{
		// php's realpath cache can still lead a removed junction to the checkout;
		// a copy onto the file it reads from empties that file
		clearstatcache(true);

		File::ensureDirectoryExists($link . '/composer');

		if ($this->isSameFolder($link, $target) || $this->isSameFolder($link . '/composer', $target . '/composer')) {
			throw new RuntimeException(sprintf('%s still leads to %s; nothing is copied.', $link, $target));
		}

		foreach (['' => ['composer', 'bin'], '/composer' => []] as $level => $copiedFolders) {
			foreach (File::files($target . $level) as $file) {
				File::copy($file->getPathname(), $link . $level . '/' . $file->getFilename());
			}

			foreach (File::directories($target . $level) as $directory) {
				$name = basename($directory);

				if (in_array($name, $copiedFolders, true) || file_exists($link . $level . '/' . $name)) {
					continue;
				}

				$this->link($directory, $link . $level . '/' . $name);
			}
		}

		// the bin proxies load the autoload next to them, so they are copied too
		File::copyDirectory($target . '/bin', $link . '/bin');

		$this->dropProjectClassMap($link . '/composer/autoload_static.php');

		$this->standScriptsHere($target, $link);
	}

	public function standScriptsHere(string $target, string $link): void
	{
		// a script such as phpunit looks for the autoload from the folder it stands in;
		// through a junction that is the checkout, whose autoload then meets the copy's
		$folders = $this->scriptFolders($target);

		foreach (array_filter($folders, fn (string $folder) => ! str_contains($folder, '/')) as $name) {
			if ($this->isLink($link . '/' . $name)) {
				$this->drop($link . '/' . $name);
			}

			if (! file_exists($link . '/' . $name) && is_dir($target . '/' . $name)) {
				$this->borrowFolder($target . '/' . $name, $link . '/' . $name, $name, $folders);
			}
		}
	}

	/**
	 * The folders under vendor that hold a package's script, with every
	 * folder above them, by their path inside vendor.
	 *
	 * @return array<int, string>
	 */
	private function scriptFolders(string $vendor): array
	{
		$installed = json_decode((string) @file_get_contents($vendor . '/composer/installed.json'), true);
		$folders = [];

		foreach ((array) ($installed['packages'] ?? $installed ?? []) as $package) {
			foreach ((array) ($package['bin'] ?? []) as $script) {
				$path = trim($package['name'] . '/' . dirname((string) $script), '/.');
				$parts = explode('/', $path);

				foreach (array_keys($parts) as $depth) {
					$folders[] = implode('/', array_slice($parts, 0, $depth + 1));
				}
			}
		}

		return array_values(array_unique($folders));
	}

	/**
	 * A real folder in the copy: its files are hard links to the checkout's,
	 * the folders on the way to a script are made the same way, and every
	 * other folder is borrowed as a whole.
	 *
	 * @param  array<int, string>  $scriptFolders
	 */
	private function borrowFolder(string $target, string $link, string $relative, array $scriptFolders): void
	{
		File::ensureDirectoryExists($link);

		foreach (File::files($target) as $file) {
			$copy = $link . '/' . $file->getFilename();

			// a hard link costs no space and still has the path of the copy
			if (! file_exists($copy) && ! @link($file->getPathname(), $copy)) {
				File::copy($file->getPathname(), $copy);
			}
		}

		foreach (File::directories($target) as $directory) {
			$name = basename($directory);
			$child = $relative . '/' . $name;

			if (file_exists($link . '/' . $name)) {
				continue;
			}

			in_array($child, $scriptFolders, true)
				? $this->borrowFolder($directory, $link . '/' . $name, $child, $scriptFolders)
				: $this->link($directory, $link . '/' . $name);
		}
	}

	/**
	 * Takes the project's own classes out of composer's class map, so they are
	 * found through PSR-4 in this copy: a class added or moved on this branch
	 * is then found where it stands now.
	 */
	private function dropProjectClassMap(string $file): void
	{
		if (! is_file($file)) {
			return;
		}

		$standing = (string) file_get_contents($file);

		$trimmed = preg_replace_callback(
			'/(public static \$classMap = array \(\r?\n)(.*?)(\r?\n\s*\);)/s',
			fn (array $parts) => $parts[1] . preg_replace("/^[ \t]*'[^']+' => __DIR__ \. '\/\.\.\/\.\.' \. '[^']*',\r?\n?/m", '', $parts[2]) . $parts[3],
			$standing,
			1,
		);

		if ($trimmed !== null && $trimmed !== $standing) {
			file_put_contents($file, $trimmed);
		}
	}

	/**
	 * Brings over the files git never carried: your own settings, your own
	 * rules, and the environment the copy runs on.
	 */
	private function carryOver(string $repositoryPath, string $path): void
	{
		foreach ((array) config('devdock.worktree.copy') as $entry) {
			$from = $repositoryPath . '/' . $entry;
			$to = $path . '/' . $entry;

			// what the commit already put there belongs to the branch
			if (! file_exists($from) || file_exists($to)) {
				continue;
			}

			File::ensureDirectoryExists(dirname($to));

			is_dir($from) ? File::copyDirectory($from, $to) : File::copy($from, $to);
		}
	}

	/**
	 * Lets a working copy run on the permissions you already gave this project.
	 * Claude keys trust on the exact path it is handed, and a working copy
	 * resolves to the checkout it belongs to, so that entry is the one that has
	 * to say yes. Without it every allow rule you set is quietly ignored.
	 */
	private function trust(string $repositoryPath): void
	{
		$home = $this->homePath();

		if ($home === null || ! is_file($home . self::CLAUDE_CONFIG)) {
			return;
		}

		$file = $home . self::CLAUDE_CONFIG;
		$settings = json_decode((string) file_get_contents($file), true);

		// the file holds every project Claude knows; an unreadable one is left
		// exactly as it is rather than rewritten from a half-understood copy
		if (! is_array($settings)) {
			return;
		}

		$key = rtrim(strtr($repositoryPath, DIRECTORY_SEPARATOR, '/'), '/');

		if (($settings['projects'][$key]['hasTrustDialogAccepted'] ?? false) === true) {
			return;
		}

		$settings['projects'][$key]['hasTrustDialogAccepted'] = true;

		$written = file_put_contents(
			$file . '.devdock',
			json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		);

		// written beside the original and moved over it, so a half written file
		// never takes the place of the one Claude reads
		if ($written !== false) {
			rename($file . '.devdock', $file);
		}
	}

	private function publish(string $path, SiteName $siteName): ?string
	{
		$sites = $this->sitesPath();

		if ($sites === null || ! is_dir($sites)) {
			return null;
		}

		$link = $sites . '/' . $siteName->name;

		if (! file_exists($link)) {
			if (! $this->link($path, $link)) {
				return null;
			}

			// a new site gets the PHP its project asks for, not the one Herd serves by default
			resolve(ProjectPhpRepositoryContract::class)->isolateSite($path, $siteName->name);
		}

		return $siteName->name . '.' . $this->tld();
	}

	private function pointAppUrl(string $path, string $host): void
	{
		$file = $path . '/.env';

		if (! is_file($file)) {
			return;
		}

		$standing = (string) file_get_contents($file);
		$pointed = preg_replace('/^APP_URL=.*$/m', 'APP_URL=http://' . $host, $standing, 1);

		if ($pointed !== null && $pointed !== $standing) {
			file_put_contents($file, $pointed);
		}
	}

	private function link(string $target, string $link): bool
	{
		if (PHP_OS_FAMILY !== 'Windows') {
			return @symlink($target, $link);
		}

		return Process::run(['cmd', '/c', 'mklink', '/J', $this->windows($link), $this->windows($target)])
			->successful();
	}

	/**
	 * Takes a link away without following it. On Windows rmdir empties out the
	 * link itself and refuses a real folder that still holds files, which is
	 * exactly the guard we want around a borrowed vendor.
	 */
	private function drop(string $link): void
	{
		if (! file_exists($link) && ! is_link($link)) {
			return;
		}

		PHP_OS_FAMILY === 'Windows'
			? Process::run(['cmd', '/c', 'rmdir', $this->windows($link)])
			: @unlink($link);

		// php's realpath cache would otherwise keep leading this path into the target
		clearstatcache(true);
	}

	private function sameFile(string $left, string $right): bool
	{
		if (! is_file($left) || ! is_file($right)) {
			return false;
		}

		return md5_file($left) === md5_file($right);
	}

	private function sitesPath(): ?string
	{
		$configured = (string) config('devdock.herd.sites');

		if ($configured !== '') {
			return rtrim(str_replace('\\', '/', $configured), '/');
		}

		$file = $this->herdConfigFile();

		return $file === null ? null : dirname($file) . '/Sites';
	}

	private function herdConfigFile(): ?string
	{
		$home = $this->homePath();

		if ($home === null) {
			return null;
		}

		$file = $home . self::HERD_CONFIG;

		return is_file($file) ? $file : null;
	}

	/**
	 * The home folder, written the way the rest of this class writes paths.
	 */
	private function homePath(): ?string
	{
		$home = (string) (getenv('USERPROFILE') ?: getenv('HOME'));

		return $home === '' ? null : rtrim(strtr($home, DIRECTORY_SEPARATOR, '/'), '/');
	}

	private function tld(): string
	{
		$configured = (string) config('devdock.herd.tld');

		if ($configured !== '') {
			return $configured;
		}

		$file = $this->herdConfigFile();
		$settings = $file === null ? [] : (array) json_decode((string) file_get_contents($file), true);

		return (string) ($settings['tld'] ?? 'test');
	}

	private function nameOf(string $site): string
	{
		$suffix = '.' . $this->tld();

		return str_ends_with($site, $suffix) ? substr($site, 0, -strlen($suffix)) : $site;
	}

	private function windows(string $path): string
	{
		return str_replace('/', '\\', $path);
	}
}
