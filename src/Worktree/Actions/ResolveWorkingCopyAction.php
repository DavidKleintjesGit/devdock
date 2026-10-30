<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * The folder a task works in. A branch that already stands checked out
 * somewhere is worked on there; anything else gets a working copy of its own.
 *
 * @method static string execute(string $project, string $branch, ?string $card)
 *
 * @throws WorktreeNotPossibleException
 */
class ResolveWorkingCopyAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly string $branch,
		public readonly ?string $card = null,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): string
	{
		$standing = $worktreeRepository->getFor($this->project, $this->branch);

		if ($standing !== null && is_dir($standing->path)) {
			return $standing->path;
		}

		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project);

		if ($projectEntity === null) {
			throw new WorktreeNotPossibleException(__('Het project :project staat niet op schijf.', [
				'project' => $this->project,
			]));
		}

		// the branch may already be open in the checkout itself, and then that is
		// simply where this task lives
		$takenAt = $worktreeRepository->checkedOutAt($projectEntity->path, $this->branch);

		if ($takenAt !== null) {
			return $takenAt;
		}

		return EnsureWorktreeAction::execute($this->project, $this->branch, $this->card)->path;
	}
}
