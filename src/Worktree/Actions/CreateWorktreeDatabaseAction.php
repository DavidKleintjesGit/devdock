<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Gives a working copy a database of its own, so what the task does no longer
 * touches the one of the project. Returns why it failed, or null.
 *
 * @method static ?string execute(WorktreeEntity $worktreeEntity, string $name, DatabaseFillEnum $fill)
 */
class CreateWorktreeDatabaseAction extends Command
{
	public function __construct(
		public readonly WorktreeEntity $worktreeEntity,
		public readonly string $name,
		public readonly DatabaseFillEnum $fill,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
	): ?string {
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->worktreeEntity->project);

		if ($projectEntity === null) {
			return __('Het project :project staat niet op schijf.', ['project' => $this->worktreeEntity->project]);
		}

		$failure = $worktreeDatabaseRepository->create($projectEntity->path, $this->worktreeEntity->path, $this->name, $this->fill);

		// even a failed fill leaves a database behind that this copy owns
		if ($worktreeDatabaseRepository->exists($this->worktreeEntity->path, $this->name)) {
			$worktreeRepository->markDatabase($this->worktreeEntity->worktreeId, $this->name);
		}

		return $failure;
	}
}
