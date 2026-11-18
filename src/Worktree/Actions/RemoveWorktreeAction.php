<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeId;

/**
 * Takes away everything DevDock put down for a working copy: the folder, the
 * borrowed packages and the site, its own databases, and the local branch
 * once origin holds it. What it found already standing, it leaves where it
 * is. The history of the task, its conversation, sessions and round reports,
 * stays for reading back.
 *
 * @method static bool execute(WorktreeId $worktreeId, bool $discardsWork = false)
 */
class RemoveWorktreeAction extends Command
{
	public function __construct(
		public readonly WorktreeId $worktreeId,
		public readonly bool $discardsWork = false,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
	): bool {
		$worktreeEntity = $worktreeRepository->getAll()->first(
			fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->worktreeId->id === $this->worktreeId->id,
		);

		if ($worktreeEntity === null) {
			return false;
		}

		// a site worker or queue that still runs in the folder holds its files open, and then removing it fails halfway
		if ($worktreeEntity->origin === WorktreeOriginEnum::MADE && is_dir($worktreeEntity->path)) {
			$workingCopyRepository->stopProcesses($worktreeEntity->path);
		}

		if ($worktreeEntity->origin->isOurs()) {
			// the borrowed folders only point at the checkout, and git empties the folder it is told to remove
			$workingCopyRepository->release($worktreeEntity->path, $worktreeEntity->site);
		}

		// the databases read their names from the copy, so they go first
		if ($worktreeEntity->ownDatabase !== null) {
			$worktreeDatabaseRepository->drop($worktreeEntity->path, $worktreeEntity->ownDatabase);
		}

		if ($worktreeEntity->origin->isOurs() && is_dir($worktreeEntity->path)) {
			$worktreeDatabaseRepository->dropTestDatabase(dirname($worktreeEntity->path, 2), $worktreeEntity->path);
		}

		return $worktreeRepository->remove($this->worktreeId, $this->discardsWork);
	}
}
