<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Enums\PullRequestStateEnum;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Marks for every working copy the branch its open pull request merges into,
 * so behind, conflicts, rebase and the code reader compare against that
 * branch. A working copy without an open pull request goes back to the main
 * line. Returns how many working copies changed target.
 *
 * @method static int execute()
 */
class FollowPullRequestTargetsAction extends Command
{
	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		BranchStandingRepositoryContract $branchStandingRepository,
	): int {
		$projectsByName = collect(GetProjectsAction::execute())->keyBy(fn (ProjectEntity $projectEntity): string => $projectEntity->name);
		$targetsBySource = collect(GetPullRequestsAction::execute())
			->filter(fn (PullRequestEntity $pullRequestEntity): bool => $pullRequestEntity->state === PullRequestStateEnum::OPEN
				&& $pullRequestEntity->sourceBranch !== null
				&& $pullRequestEntity->destinationBranch !== null)
			->mapWithKeys(fn (PullRequestEntity $pullRequestEntity): array => [
				$pullRequestEntity->repository . '|' . $pullRequestEntity->sourceBranch => $pullRequestEntity->destinationBranch,
			]);

		$changedWorkingCopies = 0;

		foreach (GetWorktreesAction::execute() as $worktreeEntity) {
			$projectEntity = $projectsByName->get($worktreeEntity->project);
			$slug = $projectEntity?->repositorySlug();

			if ($projectEntity === null || $slug === null || ! is_dir($projectEntity->path)) {
				continue;
			}

			if ($worktreeRepository->setTargetBranch($projectEntity->path, $worktreeEntity->branch, $targetsBySource->get($slug . '|' . $worktreeEntity->branch))) {
				$worktreeRepository->forgetStatus($worktreeEntity->path);
				$branchStandingRepository->forget($projectEntity->path, $worktreeEntity->branch);
				$changedWorkingCopies++;
			}
		}

		return $changedWorkingCopies;
	}
}
