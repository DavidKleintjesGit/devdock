<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Src\Packages\Process\ProjectEnvironment;
use Src\Project\Contracts\ProjectPhpRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyStateRepositoryContract;
use Src\Worktree\Enums\ProjectKindEnum;
use Src\Worktree\Objects\MigrationCountObject;
use Src\Worktree\Objects\WorkingCopyStateObject;

class WorkingCopyStateRepository implements WorkingCopyStateRepositoryContract
{
	/**
	 * Lines of a failed step that come back as the reason; the rest is in the log.
	 */
	private const FAILURE_LINES = 12;

	public function read(string $repositoryPath, string $path, ProjectKindEnum $projectKind, array $carried, array $shared, array $ownFiles): WorkingCopyStateObject
	{
		$environment = $this->environmentOf($path . '/.env');
		$composerLock = $shared['vendor'] ?? 'composer.lock';
		$packageLock = $shared['node_modules'] ?? 'package-lock.json';
		$gitDirectory = $this->gitDirectory($path);
		$commonDirectory = $this->commonDirectory($gitDirectory);
		$currentBranch = $this->currentBranch($gitDirectory);

		return new WorkingCopyStateObject(
			currentBranch: $currentBranch,
			upstream: $currentBranch === null ? null : $this->upstreamOf($commonDirectory, $currentBranch),
			mainBranch: $this->mainBranch($commonDirectory),
			operationInProgress: $this->operationInProgress($gitDirectory),
			missingCarried: array_values(array_filter(
				$carried,
				fn (string $entry): bool => file_exists($repositoryPath . '/' . $entry) && ! file_exists($path . '/' . $entry),
			)),
			missingExcludes: $this->missingExcludes($commonDirectory, $ownFiles),
			hasComposer: is_file($path . '/composer.json'),
			hasVendorAutoload: is_file($path . '/vendor/autoload.php'),
			isVendorBorrowed: $this->isBorrowed($path . '/vendor'),
			missingVendorPackages: $this->isBorrowed($path . '/vendor') ? $this->missingPackages($repositoryPath . '/vendor', $path . '/vendor') : [],
			isComposerLockSame: $this->isSameFile($repositoryPath . '/' . $composerLock, $path . '/' . $composerLock),
			hasPackageJson: is_file($path . '/package.json'),
			hasNodeModules: is_dir($path . '/node_modules'),
			isNodeModulesBorrowed: $this->isBorrowed($path . '/node_modules'),
			isPackageLockSame: $this->isSameFile($repositoryPath . '/' . $packageLock, $path . '/' . $packageLock),
			hasPackageLock: is_file($path . '/' . $packageLock),
			hasAssetManifest: $projectKind->assetManifest() !== null && is_file($path . '/' . $projectKind->assetManifest()),
			hasAppKey: filled($environment['APP_KEY'] ?? null),
			needsStorageLink: file_exists($repositoryPath . '/public/storage'),
			hasStorageLink: file_exists($path . '/public/storage'),
			appUrl: $environment['APP_URL'] ?? null,
		);
	}

	public function countPendingMigrations(string $path, bool $isTesting = false): MigrationCountObject
	{
		$result = Process::path($path)
			->env([...ProjectEnvironment::withoutDevDock(), ...ProjectEnvironment::withPhpFirst(resolve(ProjectPhpRepositoryContract::class)->binaryFor($path))])
			->timeout(120)
			->run([resolve(ProjectPhpRepositoryContract::class)->binaryFor($path), 'artisan', 'migrate:status', '--no-ansi', ...($isTesting ? ['--env=testing'] : [])]);

		$output = $result->output() . $result->errorOutput();

		// a database without the migrations table has run none of them
		if (str_contains($output, 'Migration table not found')) {
			return new MigrationCountObject(pending: count(glob($path . '/database/migrations/*.php') ?: []), failure: null);
		}

		if (! $result->successful()) {
			$lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
			$telling = collect($lines)->first(fn (string $line): bool => preg_match('/error|exception|failed/i', $line) === 1) ?? ($lines[0] ?? '');

			return new MigrationCountObject(pending: null, failure: mb_substr($telling, 0, 300));
		}

		// Laravel 9 and later write Pending, older versions a No in the Ran column
		return new MigrationCountObject(pending: preg_match_all('/\bPending\b|^\|\s*No\s*\|/m', $output), failure: null);
	}

	public function runStep(string $path, array $command, string $logPath, int $timeout): ?string
	{
		File::ensureDirectoryExists(dirname($logPath));
		File::append($logPath, '$ ' . implode(' ', $command) . PHP_EOL);

		$result = Process::path($path)
			->env([...ProjectEnvironment::withoutDevDock(), ...ProjectEnvironment::withPhpFirst(resolve(ProjectPhpRepositoryContract::class)->binaryFor($path))])
			->timeout($timeout)
			->run($command, function (string $type, string $output) use ($logPath): void {
				File::append($logPath, $output);
			});

		if ($result->successful()) {
			return null;
		}

		$lines = preg_split('/\R/', trim($result->errorOutput() ?: $result->output())) ?: [];

		return implode("\n", array_slice($lines, -self::FAILURE_LINES)) ?: __('De stap liep vast zonder uitleg.');
	}

	/**
	 * The lines of the exclude of the repository that are not there yet.
	 *
	 * @param  list<string>  $lines
	 * @return list<string>
	 */
	private function missingExcludes(?string $commonDirectory, array $lines): array
	{
		$standing = $commonDirectory === null ? '' : (string) @file_get_contents($commonDirectory . '/info/exclude');

		return array_values(array_filter(
			$lines,
			fn (string $line): bool => preg_match('/^' . preg_quote($line, '/') . '\r?$/m', $standing) !== 1,
		));
	}

	/**
	 * The package folders of the project that a borrowed vendor lacks.
	 *
	 * @return list<string>
	 */
	private function missingPackages(string $projectVendor, string $vendor): array
	{
		return array_values(array_filter(
			@scandir($projectVendor) ?: [],
			fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($projectVendor . '/' . $entry) && ! file_exists($vendor . '/' . $entry),
		));
	}

	/**
	 * The branch checked out, read from HEAD; null when HEAD points at a
	 * commit instead of a branch.
	 */
	private function currentBranch(?string $gitDirectory): ?string
	{
		$head = $gitDirectory === null ? '' : (string) @file_get_contents($gitDirectory . '/HEAD');

		return preg_match('#^ref: refs/heads/(.+)$#m', $head, $found) === 1 ? trim($found[1]) : null;
	}

	/**
	 * The upstream of a branch as git status names it, read from the remote
	 * and merge lines of its section in the config of the repository.
	 */
	private function upstreamOf(?string $commonDirectory, string $branch): ?string
	{
		$config = $commonDirectory === null ? '' : (string) @file_get_contents($commonDirectory . '/config');

		if (preg_match('/^\[branch "' . preg_quote($branch, '/') . '"\]\R((?:(?!\[).*\R?)*)/m', $config, $section) !== 1) {
			return null;
		}

		$remote = preg_match('/^\s*remote\s*=\s*(\S+)/m', $section[1], $found) === 1 ? $found[1] : null;
		$merge = preg_match('#^\s*merge\s*=\s*refs/heads/(\S+)#m', $section[1], $found) === 1 ? $found[1] : null;

		return $remote === null || $merge === null ? null : ($remote === '.' ? $merge : $remote . '/' . $merge);
	}

	/**
	 * The folder all worktrees of a repository share, where its config and
	 * remote branches stand.
	 */
	private function commonDirectory(?string $gitDirectory): ?string
	{
		if ($gitDirectory === null) {
			return null;
		}

		$commonFile = $gitDirectory . '/commondir';

		return is_file($commonFile) ? $gitDirectory . '/' . trim((string) file_get_contents($commonFile)) : $gitDirectory;
	}

	/**
	 * The folder git keeps this working copy's state in. A worktree points
	 * there from a .git file instead of holding a .git folder.
	 */
	private function gitDirectory(string $path): ?string
	{
		if (is_dir($path . '/.git')) {
			return $path . '/.git';
		}

		if (! is_file($path . '/.git') || preg_match('/^gitdir:\s*(.+)$/m', (string) file_get_contents($path . '/.git'), $found) !== 1) {
			return null;
		}

		$gitDirectory = trim($found[1]);

		return preg_match('#^([A-Za-z]:)?[/\\\\]#', $gitDirectory) === 1 ? $gitDirectory : $path . '/' . $gitDirectory;
	}

	/**
	 * The branch origin names as its main line, read from the shared folder
	 * of the repository the working copy belongs to.
	 */
	private function mainBranch(?string $commonDirectory): string
	{
		$remoteHead = $commonDirectory === null ? '' : (string) @file_get_contents($commonDirectory . '/refs/remotes/origin/HEAD');

		return preg_match('#ref: refs/remotes/origin/(.+)#', $remoteHead, $found) === 1 ? trim($found[1]) : 'main';
	}

	/**
	 * A rebase, merge or cherry-pick that stopped halfway, read from the
	 * markers git leaves in the folder of this working copy.
	 */
	private function operationInProgress(?string $gitDirectory): ?string
	{
		return match (true) {
			$gitDirectory === null => null,
			is_dir($gitDirectory . '/rebase-merge'), is_dir($gitDirectory . '/rebase-apply') => 'rebase',
			is_file($gitDirectory . '/MERGE_HEAD') => 'merge',
			is_file($gitDirectory . '/CHERRY_PICK_HEAD') => 'cherry-pick',
			default => null,
		};
	}

	/**
	 * Whether a package folder is the project's: one link to it, or a real
	 * folder whose packages are links, as a borrowed vendor is.
	 */
	private function isBorrowed(string $folder): bool
	{
		if (! file_exists($folder)) {
			return false;
		}

		if ($this->isLink($folder)) {
			return true;
		}

		foreach (@scandir($folder) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..' && $this->isLink($folder . '/' . $entry)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A link, or on Windows a junction, which php does not call a link.
	 */
	private function isLink(string $path): bool
	{
		$real = realpath($path);

		return is_link($path) || ($real !== false && strcasecmp(str_replace('\\', '/', $real), str_replace('\\', '/', $path)) !== 0);
	}

	private function isSameFile(string $left, string $right): bool
	{
		return is_file($left) && is_file($right) && md5_file($left) === md5_file($right);
	}

	/**
	 * @return array<string, string>
	 */
	private function environmentOf(string $file): array
	{
		return is_file($file) ? Dotenv::parse((string) file_get_contents($file)) : [];
	}
}
