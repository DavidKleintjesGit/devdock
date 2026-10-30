<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\PullRequest\Enums\PullRequestStateEnum;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Where a task stands on its way from nothing to merged. The rounds decide
 * the middle of that path: no round means nothing has been handed over yet.
 *
 * @method static WorkflowStepEnum execute(CardObject $cardObject, ?ReviewRoundEntity $latestRound)
 */
class ResolveWorkflowStepAction extends Command
{
	public function __construct(
		public readonly CardObject $cardObject,
		public readonly ?ReviewRoundEntity $latestRound,
	) {}

	public function handle(): WorkflowStepEnum
	{
		if ($this->isMerged()) {
			return WorkflowStepEnum::MERGED;
		}

		if ($this->latestRound?->status === RoundStatusEnum::PASSED) {
			return WorkflowStepEnum::READY_TO_MERGE;
		}

		// a round that was broken off does not count: the task stands where it did
		if ($this->latestRound !== null && $this->latestRound->status !== RoundStatusEnum::STOPPED) {
			return WorkflowStepEnum::REVIEW;
		}

		// a pull request is a delivery: the work has left the working copy
		return $this->cardObject->activePullRequest === null
			? WorkflowStepEnum::BUILDING
			: WorkflowStepEnum::DELIVERED;
	}

	private function isMerged(): bool
	{
		$merged = $this->cardObject->pullRequests->first(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->state === PullRequestStateEnum::MERGED
				&& $pullRequestEntity->repository . '#' . $pullRequestEntity->number === $this->cardObject->activePullRequest,
		);

		return $merged !== null;
	}
}
