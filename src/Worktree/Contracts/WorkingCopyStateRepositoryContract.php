<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Src\Worktree\Enums\ProjectKindEnum;
use Src\Worktree\Objects\MigrationCountObject;
use Src\Worktree\Objects\WorkingCopyStateObject;

interface WorkingCopyStateRepositoryContract
{
	/**
	 * What stands in the working copy compared with the checkout it came from.
	 *
	 * @param  list<string>  $carried  the files a working copy takes over from the project
	 * @param  array<string, string>  $shared  the package folders a working copy may borrow, with their lock file
	 * @param  list<string>  $ownFiles  the exclude lines for the files DevDock writes into a copy
	 */
	public function read(string $repositoryPath, string $path, ProjectKindEnum $projectKind, array $carried, array $shared, array $ownFiles): WorkingCopyStateObject;

	/**
	 * How many migrations have not run on the database of the working copy, or
	 * on its test database, or why artisan could not tell.
	 */
	public function countPendingMigrations(string $path, bool $isTesting = false): MigrationCountObject;

	/**
	 * Runs one step of a repair in the working copy and appends what it said
	 * to the log. Returns the line that says what went wrong when it failed,
	 * null when it went through.
	 *
	 * @param  list<string>  $command
	 */
	public function runStep(string $path, array $command, string $logPath, int $timeout): ?string;
}
