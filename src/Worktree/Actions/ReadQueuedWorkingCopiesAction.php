<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Reads the working copies in line again, one after the other, so the next
 * page finds them fresh without waiting on git itself.
 *
 * @method static void execute()
 */
class ReadQueuedWorkingCopiesAction extends Command
{
	public function handle(WorktreeRepositoryContract $worktreeRepository): void
	{
		$worktreeRepository->readQueuedCopies();
	}
}
