<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Task\Actions\ActivateLinkAction;
use Src\Task\Actions\LinkToTaskAction;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * Links a task to a working copy of its own on the branch you picked, the one
 * way a task gets a place to work. A copy DevDock made is used as it stands.
 * Otherwise DevDock makes one: when the branch is open in the project itself,
 * the project goes back to its main line first, refused while it holds work
 * that is not committed unless that work is carried along; a branch checked
 * out in a folder outside DevDock is refused, since git allows one folder per
 * branch. The checklist of the copy is repaired in the background right away,
 * so it ends up complete. Returns why it could not, or null.
 *
 * @method static ?string execute(string $card, string $project, string $branch, ?DatabaseFillEnum $fill = null, bool $carriesChanges = false)
 */
class LinkWorkingCopyAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly string $project,
		public readonly string $branch,
		// an empty database or a migrated one; null copies the database of the project
		public readonly ?DatabaseFillEnum $fill = null,
		public readonly bool $carriesChanges = false,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
	): ?string
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $this->project)
			?->path;

		if ($projectPath === null) {
			return __('Het project :project staat niet op schijf.', ['project' => $this->project]);
		}

		$known = $worktreeRepository->getFor($this->project, $this->branch);

		// a link to the project itself or to a folder made elsewhere is no working copy of DevDock; only the record goes, the folder is left as it is
		if ($known !== null && $known->origin !== WorktreeOriginEnum::MADE) {
			$worktreeRepository->remove($known->worktreeId);
			$known = null;
		}

		try {
			$path = $known !== null && is_dir($known->path) ? $known->path : $this->makeNew($worktreeRepository, $worktreeDatabaseRepository, $projectPath);
		} catch (WorktreeNotPossibleException $refusal) {
			return $refusal->getMessage();
		}

		LinkToTaskAction::execute($this->card, TaskLinkKindEnum::PROJECT, $this->project);
		LinkToTaskAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->branch);
		ActivateLinkAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->branch);

		if (! CheckReadinessAction::execute($path)->isReady()) {
			StartRepairAction::execute($path);
		}

		return null;
	}

	/**
	 * @throws WorktreeNotPossibleException
	 */
	private function makeNew(
		WorktreeRepositoryContract $worktreeRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
		string $projectPath,
	): string
	{
		$standsAt = $worktreeRepository->checkedOutAt($projectPath, $this->branch);

		if ($standsAt !== null && CheckReadinessAction::normalize($standsAt) === CheckReadinessAction::normalize($projectPath)) {
			$failure = $worktreeRepository->switchToMain($projectPath, $this->carriesChanges);

			if ($failure !== null) {
				throw new WorktreeNotPossibleException($failure);
			}
		} elseif ($standsAt !== null) {
			throw new WorktreeNotPossibleException(__('De branch :branch staat uitgecheckt in :path, buiten DevDock. Zet die map op een andere branch of haal hem weg; daarna maakt DevDock een eigen werkkopie.', [
				'branch' => $this->branch,
				'path' => $standsAt,
			]));
		}

		$worktreeEntity = EnsureWorktreeAction::execute($this->project, $this->branch, $this->card);

		if ($this->carriesChanges) {
			$failure = $worktreeRepository->layBackCarried($worktreeEntity->path);

			if ($failure !== null) {
				throw new WorktreeNotPossibleException($failure);
			}
		}

		// a copy of the database is what the repair makes; an empty or migrated one is made here
		if ($this->fill !== null && $this->fill !== DatabaseFillEnum::COPY) {
			$failure = CreateWorktreeDatabaseAction::execute(
				$worktreeEntity,
				$worktreeDatabaseRepository->describe($projectPath, $worktreeEntity->path)->suggestionFor(basename($this->branch)),
				$this->fill,
			);

			if ($failure !== null) {
				throw new WorktreeNotPossibleException($failure);
			}
		}

		return $worktreeEntity->path;
	}
}
