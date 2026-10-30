<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Sends the commits of a working copy to the remote. This is the one step
 * that leaves the machine, so it never happens on its own.
 *
 * @method static bool execute(string $directory)
 */
class PushBranchAction extends Command
{
	public function __construct(
		public readonly string $directory,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): bool
	{
		return $worktreeRepository->push($this->directory);
	}
}
