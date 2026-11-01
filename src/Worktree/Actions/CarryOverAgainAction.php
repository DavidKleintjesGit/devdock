<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Copies the ignored files of the project into the working copy again, for
 * when they changed there after the copy was made.
 *
 * @method static bool execute(WorktreeEntity $worktreeEntity)
 */
class CarryOverAgainAction extends Command
{
	public function __construct(
		public readonly WorktreeEntity $worktreeEntity,
	) {}

	public function handle(WorkingCopyRepositoryContract $workingCopyRepository): bool
	{
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->worktreeEntity->project);

		if ($projectEntity === null) {
			return false;
		}

		$workingCopyRepository->carryOverAgain($projectEntity->path, $this->worktreeEntity->path);

		return true;
	}
}
