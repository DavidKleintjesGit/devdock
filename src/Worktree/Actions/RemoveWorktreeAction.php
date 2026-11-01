<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeId;

/**
 * Takes away everything DevDock put down for a working copy: the folder, the
 * borrowed packages and the site, its own database, the branch it created
 * once that is merged, and the sessions Claude kept for the folder. What it
 * found already standing, it leaves where it is.
 *
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

		// the project itself borrows nothing; its own packages are real folders
		if ($worktreeEntity->origin !== WorktreeOriginEnum::PROJECT) {
			// the borrowed folders only point at the checkout, and git empties the
			// folder it is told to remove
			$workingCopyRepository->release($worktreeEntity->path, $worktreeEntity->site);
		}

		// the database read its name from the copy, so it goes first
		if ($worktreeEntity->ownDatabase !== null) {
			resolve(WorktreeDatabaseRepositoryContract::class)->drop($worktreeEntity->path, $worktreeEntity->ownDatabase);
		}

		if (! $worktreeRepository->remove($this->worktreeId)) {
			return false;
		}

		if ($worktreeEntity->origin->isOurs()) {
			resolve(SessionFileRepositoryContract::class)->deleteFor($worktreeEntity->path);
		}

		return true;
	}
}
