<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * The working copy of DevDock a task works in, made when it has none yet. A
 * branch that stands checked out somewhere else is refused: it is linked on
 * the Working copy tab, which moves it into a copy of its own.
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

		// a branch open in the project or in a folder outside DevDock is linked on the Working copy tab, never worked in where it stands
		$takenAt = $worktreeRepository->checkedOutAt($projectEntity->path, $this->branch);

		if ($takenAt !== null) {
			throw new WorktreeNotPossibleException(__('De branch :branch staat uitgecheckt in :path. Koppel de taak op de tab Werkkopie; dan krijgt hij een eigen werkkopie.', [
				'branch' => $this->branch,
				'path' => $takenAt,
			]));
		}

		return EnsureWorktreeAction::execute($this->project, $this->branch, $this->card)->path;
	}
}
