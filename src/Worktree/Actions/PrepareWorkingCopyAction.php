<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Task\Actions\ActivateLinkAction;
use Src\Task\Actions\LinkToTaskAction;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * Sets the working copy of a task ready on the branch you picked: the one
 * that already stands, the project itself when the branch is open there, or
 * a new one with the database you chose. Either way the task is tied to that
 * branch from here on. Returns why it could not, or null.
 *
 * @method static ?string execute(string $card, string $project, string $branch, ?DatabaseFillEnum $fill, string $databaseName)
 */
class PrepareWorkingCopyAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly string $project,
		public readonly string $branch,
		// null deelt de database van het project
		public readonly ?DatabaseFillEnum $fill,
		public readonly string $databaseName,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): ?string
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			?->path;

		if ($projectPath === null) {
			return __('Het project :project staat niet op schijf.', ['project' => $this->project]);
		}

		$known = $worktreeRepository->getFor($this->project, $this->branch);
		$standsAt = $known !== null && is_dir($known->path) ? null : $worktreeRepository->checkedOutAt($projectPath, $this->branch);
		$failure = null;

		try {
			if ($known === null || ! is_dir($known->path)) {
				$failure = $standsAt === null
					? $this->makeNew()
					: $this->adopt($worktreeRepository, $standsAt, $projectPath);
			}
		} catch (WorktreeNotPossibleException $exception) {
			return $exception->getMessage();
		}

		LinkToTaskAction::execute($this->card, TaskLinkKindEnum::PROJECT, $this->project);
		LinkToTaskAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->branch);
		ActivateLinkAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->branch);

		return $failure;
	}

	private function makeNew(): ?string
	{
		$worktreeEntity = EnsureWorktreeAction::execute($this->project, $this->branch, $this->card);

		return $this->fill === null
			? null
			: CreateWorktreeDatabaseAction::execute($worktreeEntity, $this->databaseName, $this->fill);
	}

	/**
	 * A folder that already holds the branch is the working copy as it is;
	 * DevDock writes it down but did not make it, so it stays when the task
	 * is cleaned up.
	 */
	private function adopt(WorktreeRepositoryContract $worktreeRepository, string $path, string $projectPath): ?string
	{
		$isProject = strtolower(rtrim(str_replace('\\', '/', $path), '/')) === strtolower(rtrim(str_replace('\\', '/', $projectPath), '/'));

		$worktreeRepository->adopt(
			$this->project,
			$this->branch,
			str_replace('\\', '/', $path),
			$isProject ? WorktreeOriginEnum::PROJECT : WorktreeOriginEnum::ADOPTED,
			$this->card,
		);

		return null;
	}
}
