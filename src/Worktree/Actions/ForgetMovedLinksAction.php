<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Lets go of a linked folder that no longer holds the branch of its task,
 * because somebody put it on another branch outside DevDock. The folder stays;
 * only the link goes, so the card stops showing the wrong branch.
 *
 * @method static int execute()
 */
class ForgetMovedLinksAction extends Command
{
	public function handle(WorktreeRepositoryContract $worktreeRepository): int
	{
		$projectPaths = GetProjectsAction::execute()
			->mapWithKeys(fn (ProjectEntity $projectEntity) => [$projectEntity->name => $projectEntity->path]);

		$placesByProject = [];
		$forgotten = 0;

		foreach (GetWorktreesAction::execute() as $worktreeEntity) {
			$projectPath = $projectPaths[$worktreeEntity->project] ?? null;

			if ($worktreeEntity->origin->isOurs() || $projectPath === null) {
				continue;
			}

			$placesByProject[$worktreeEntity->project] ??= $worktreeRepository->getPlaces($projectPath);
			$place = $placesByProject[$worktreeEntity->project][$worktreeEntity->branch] ?? null;

			if ($place !== null && $this->samePath($place, $worktreeEntity)) {
				continue;
			}

			$forgotten += (int) $worktreeRepository->remove($worktreeEntity->worktreeId);
		}

		return $forgotten;
	}

	private function samePath(string $place, WorktreeEntity $worktreeEntity): bool
	{
		return strcasecmp(
			rtrim(str_replace('\\', '/', $place), '/'),
			rtrim(str_replace('\\', '/', $worktreeEntity->path), '/'),
		) === 0;
	}
}
