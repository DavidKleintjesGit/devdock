<?php

declare(strict_types=1);

namespace Src\Board\Policies;

use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Objects\BoardColumnFactsObject;

/**
 * The rule for the column of a task, from the furthest step its work reached:
 * a pull request that was merged or a ticked-off to-do is done, an open pull
 * request waits there, a task stays in review from its first round until a
 * pull request exists, a working copy means building, and a branch, session
 * or agent without one is not set up yet. Untouched work is for later or to do.
 */
class BoardColumnPolicy
{
	public function decide(BoardColumnFactsObject $boardColumnFactsObject): BoardColumnEnum
	{
		return match (true) {
			$boardColumnFactsObject->isTickedOff,
			$boardColumnFactsObject->hasPullRequest && ! $boardColumnFactsObject->hasOpenPullRequest => BoardColumnEnum::DONE,
			$boardColumnFactsObject->hasOpenPullRequest => BoardColumnEnum::PULL_REQUEST,
			$boardColumnFactsObject->hasReviewRound => BoardColumnEnum::REVIEW,
			$boardColumnFactsObject->hasWorkingCopy => BoardColumnEnum::BUILDING,
			$boardColumnFactsObject->isStarted => BoardColumnEnum::NOT_SET_UP,
			$boardColumnFactsObject->isForLater => BoardColumnEnum::LATER,
			default => BoardColumnEnum::TO_DO,
		};
	}
}
