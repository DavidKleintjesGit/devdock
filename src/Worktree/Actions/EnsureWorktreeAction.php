<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Objects\SiteName;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeObject;

/**
 * The working copy a task runs in. One branch is one task, so one branch gets
 * one working copy, and every round of that task lands in the same folder.
 *
 * @method static WorktreeEntity execute(string $project, string $branch, ?string $card)
 *
 * @throws WorktreeNotPossibleException
 */
class EnsureWorktreeAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly string $branch,
		public readonly ?string $card = null,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
	): WorktreeEntity {
		$standing = $worktreeRepository->getFor($this->project, $this->branch);

		if ($standing !== null && is_dir($standing->path)) {
			return $standing;
		}

		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project);

		if ($projectEntity === null) {
			throw new WorktreeNotPossibleException(__('Het project :project staat niet op schijf.', [
				'project' => $this->project,
			]));
		}

		$worktreeEntity = $worktreeRepository->create(WorktreeObject::from([
			'project' => $this->project,
			'repository_path' => $projectEntity->path,
			'branch' => $this->branch,
			'path' => $this->pathFor($projectEntity->path),
			'card' => $this->card,
		]));

		// git hands over a bare checkout: everything the project ignores has to
		// be put there afterwards before anything can run
		$host = $workingCopyRepository->prepare(
			$projectEntity->path,
			$worktreeEntity->path,
			SiteName::for($this->project, $this->branch),
		);

		return $worktreeRepository->pinSite($worktreeEntity->worktreeId, $host);
	}

	/**
	 * Working copies stand inside the checkout they belong to. The packages
	 * are then one folder up instead of across the disk, and the branch name
	 * becomes one folder name, so nothing is left behind when it goes.
	 */
	private function pathFor(string $repositoryPath): string
	{
		$folder = str_replace(['/', '\\'], '-', $this->branch);
		$worktrees = trim((string) config('devdock.worktree.folder'), '/');

		return rtrim(str_replace('\\', '/', $repositoryPath), '/') . '/' . $worktrees . '/' . $folder;
	}
}
