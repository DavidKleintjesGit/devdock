<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Src\Packages\Process\LocalCommand;
use Symfony\Component\Process\Process;

/**
 * A git checkout in the temp folder with a bare repository beside it as its
 * origin, so branches, worktrees and pushes behave as they do on a real
 * project without reaching any network.
 */
class TemporaryGitRepository
{
	/**
	 * The first repository of a test run, copied for every one after it:
	 * setting one up takes a dozen git calls, copying it takes one.
	 */
	private static ?self $template = null;

	private function __construct(
		public readonly string $root,
		public readonly string $path,
		public readonly string $originPath,
	) {}

	/**
	 * A checkout on main with one commit, already pushed to its origin.
	 */
	public static function create(): self
	{
		if (self::$template === null) {
			self::$template = self::build(self::newRoot());
			register_shutdown_function(fn (): null => self::$template?->remove());
		}

		$root = self::newRoot();
		$repository = new self($root, $root . '/checkout', $root . '/origin.git');

		self::copyDirectory(self::$template->root, $root);
		$repository->git('remote', 'set-url', 'origin', $repository->originPath);

		return $repository;
	}

	private static function newRoot(): string
	{
		return str_replace('\\', '/', sys_get_temp_dir()) . '/devdock-tests/git-' . Str::random(12);
	}

	private static function build(string $root): self
	{
		$repository = new self($root, $root . '/checkout', $root . '/origin.git');

		mkdir($repository->path, 0777, true);
		$repository->run($root, ['git', 'init', '--quiet', '--bare', '--initial-branch=main', $repository->originPath]);
		$repository->git('init', '--quiet', '--initial-branch=main');
		$repository->git('config', 'user.name', 'DevDock Test');
		$repository->git('config', 'user.email', 'test@devdock.test');
		$repository->git('config', 'core.autocrlf', 'false');
		$repository->git('remote', 'add', 'origin', $repository->originPath);
		$repository->commit('README.md', "# test\n", 'Start');
		$repository->git('push', '--quiet', '-u', 'origin', 'main');
		$repository->git('remote', 'set-head', 'origin', 'main');

		return $repository;
	}

	private static function copyDirectory(string $source, string $target): void
	{
		mkdir($target, 0777, true);

		$entries = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
		);

		foreach ($entries as $entry) {
			$destination = $target . '/' . substr(str_replace('\\', '/', $entry->getPathname()), strlen($source) + 1);
			$entry->isDir() ? mkdir($destination) : copy($entry->getPathname(), $destination);
		}
	}

	public function write(string $file, string $contents): void
	{
		$target = $this->path . '/' . $file;

		if (! is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}

		file_put_contents($target, $contents);
	}

	public function commit(string $file, string $contents, string $message): void
	{
		$this->write($file, $contents);
		$this->git('add', $file);
		$this->git('commit', '--quiet', '-m', $message);
	}

	/**
	 * A local branch at the current commit; the checkout stays where it is.
	 */
	public function branch(string $name): void
	{
		$this->git('branch', $name);
	}

	public function checkout(string $branch): void
	{
		$this->git('checkout', '--quiet', $branch);
	}

	/**
	 * Cuts a new branch from the current commit and checks it out, without an upstream.
	 */
	public function startBranch(string $branch): void
	{
		$this->git('checkout', '--quiet', '-b', $branch);
	}

	/**
	 * Adds a git worktree on a new branch inside this checkout, the way
	 * DevDock lays one down, and returns its path.
	 */
	public function addWorktree(string $branch): string
	{
		$path = $this->path . '/.worktrees/' . str_replace('/', '-', $branch);
		$this->git('worktree', 'add', '--quiet', '-b', $branch, $path);

		return $path;
	}

	/**
	 * Pushes the branch to origin and takes the local one away, so it only
	 * stands on the remote.
	 */
	public function moveToOrigin(string $branch): void
	{
		$this->git('push', '--quiet', 'origin', $branch);
		$this->git('branch', '--quiet', '-D', $branch);
	}

	/**
	 * The branch the given one follows, or null when it follows none.
	 */
	public function upstreamOf(string $branch): ?string
	{
		$localCommandResult = LocalCommand::run($this->path, ['git', 'rev-parse', '--abbrev-ref', $branch . '@{upstream}']);

		return $localCommandResult->successful() ? trim($localCommandResult->output()) : null;
	}

	/**
	 * The branch checked out in a folder, such as a worktree of this checkout.
	 */
	public function branchIn(string $path): string
	{
		return $this->run($path, ['git', 'branch', '--show-current']);
	}

	/**
	 * Whether a git command goes through when it runs with these extra
	 * environment variables, as a process started by DevDock would.
	 *
	 * @param  array<string, string>  $environment
	 */
	public function gitSucceedsWith(array $environment, string ...$arguments): bool
	{
		$process = new Process(['git', ...$arguments], $this->path, $environment);
		$process->run();

		return $process->isSuccessful();
	}

	public function git(string ...$arguments): string
	{
		return $this->run($this->path, ['git', ...$arguments]);
	}

	public function remove(): void
	{
		if (! is_dir($this->root)) {
			return;
		}

		// git marks its object files read-only, which Windows refuses to delete
		$entries = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($entries as $entry) {
			chmod($entry->getPathname(), 0777);
			$entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}

		rmdir($this->root);
	}

	/**
	 * @param  list<string>  $command
	 */
	private function run(string $directory, array $command): string
	{
		$localCommandResult = LocalCommand::run($directory, $command);

		if (! $localCommandResult->successful()) {
			throw new RuntimeException(implode(' ', $command) . ': ' . $localCommandResult->errorOutput());
		}

		return trim($localCommandResult->output());
	}
}
