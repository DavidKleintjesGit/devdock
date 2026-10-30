<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntityCollection;

/**
 * @method static WorktreeEntityCollection execute()
 */
class GetWorktreesAction extends Command
{
	public function handle(WorktreeRepositoryContract $worktreeRepository): WorktreeEntityCollection
	{
		return $worktreeRepository->getAll();
	}
}
