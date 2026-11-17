<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use RuntimeException;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\RebaseOutcomeEnum;
use Src\Worktree\Objects\RebaseOutcomeObject;

/**
 * Moves the work of a task onto another branch to build on: the open pull
 * request merges there from now on, the branch remembers the choice, and its
 * own commits are rebased onto the new target. Work that is not committed yet
 * is carried along; a committed branch that runs into conflicts goes to the
 * agent for one turn, as with "Conflicten oplossen".
 *
 * @method static RebaseOutcomeObject execute(string $card, string $project, string $branch, ?string $ticketKey, string $directory, string $target)
 */
class RetargetWorkingCopyAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly string $project,
		public readonly string $branch,
		public readonly ?string $ticketKey,
		public readonly string $directory,
		public readonly string $target,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		BranchStandingRepositoryContract $branchStandingRepository,
		PullRequestSourceRepositoryContract $pullRequestSourceRepository,
	): RebaseOutcomeObject {
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $this->project);

		if ($projectEntity === null) {
			return new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $this->target, failure: __('Het project :project is niet bekend.', ['project' => $this->project]));
		}

		$pullRequestEntity = GetPullRequestsAction::execute()
			->first(fn (PullRequestEntity $pullRequestEntity): bool => $pullRequestEntity->isOpen()
				&& $pullRequestEntity->repository === $projectEntity->repositorySlug()
				&& $pullRequestEntity->sourceBranch === $this->branch);

		if ($pullRequestEntity !== null && $pullRequestEntity->destinationBranch !== $this->target) {
			try {
				$pullRequestSourceRepository->changeDestination($pullRequestEntity->repository, $pullRequestEntity->number, $this->target);
			} catch (RuntimeException $refusal) {
				return new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $this->target, failure: __('De pull request kon niet naar :target: :reason', ['target' => $this->target, 'reason' => $refusal->getMessage()]));
			}
		}

		// where the own commits begin is read against the old target, before the mark moves
		$ownBase = $worktreeRepository->getBranchBase($this->directory);

		$worktreeRepository->setTargetBranch($projectEntity->path, $this->branch, $this->target, isChosen: true);

		if ($ownBase !== null) {
			$worktreeRepository->keepOwnBase($projectEntity->path, $this->branch, $ownBase);
		}

		$worktreeRepository->forgetStatus($this->directory);
		$branchStandingRepository->forget($projectEntity->path, $this->branch);

		$rebaseOutcomeObject = ($worktreeRepository->getFor($this->project, $this->branch)?->changedFiles ?? 0) > 0
			? $this->rebaseCarryingWork($worktreeRepository)
			: ResolveConflictsAction::execute($this->card, $this->project, $this->branch, $this->ticketKey, $this->directory);

		if ($rebaseOutcomeObject->outcome === RebaseOutcomeEnum::DONE) {
			$worktreeRepository->rememberBase($projectEntity->path, $this->branch);
		}

		return $rebaseOutcomeObject;
	}

	private function rebaseCarryingWork(WorktreeRepositoryContract $worktreeRepository): RebaseOutcomeObject
	{
		$failure = $worktreeRepository->rebase($this->directory);

		return $failure === null
			? new RebaseOutcomeObject(RebaseOutcomeEnum::DONE, $this->target)
			: new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $this->target, failure: $failure);
	}
}
