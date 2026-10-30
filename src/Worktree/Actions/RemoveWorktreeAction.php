<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeId;

/**
 * @method static bool execute(WorktreeId $worktreeId)
 */
class RemoveWorktreeAction extends Command
{
	public function __construct(
		public readonly WorktreeId $worktreeId,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
	): bool {
		$worktreeEntity = $worktreeRepository->getAll()->first(
			fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->worktreeId->id === $this->worktreeId->id,
		);

		if ($worktreeEntity === null) {
			return false;
		}

		// the borrowed folders only point at the checkout, and git empties the
		// folder it is told to remove
		$workingCopyRepository->release($worktreeEntity->path, $worktreeEntity->site);

		return $worktreeRepository->remove($this->worktreeId);
	}
}
