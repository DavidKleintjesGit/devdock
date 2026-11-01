<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;

/**
 * Points a working copy at a database that already exists on the server.
 *
 * @method static void execute(string $directory, string $name)
 */
class UseWorktreeDatabaseAction extends Command
{
	public function __construct(
		public readonly string $directory,
		public readonly string $name,
	) {}

	public function handle(WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository): void
	{
		$worktreeDatabaseRepository->use($this->directory, $this->name);
	}
}
