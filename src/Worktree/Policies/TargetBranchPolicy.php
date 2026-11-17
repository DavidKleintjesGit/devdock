<?php

declare(strict_types=1);

namespace Src\Worktree\Policies;

use Src\Worktree\Enums\TargetSourceEnum;
use Src\Worktree\Objects\TargetFactsObject;

/**
 * The rule for the branch a working copy merges into: an open pull request
 * names it, a target chosen by hand holds while there is none, and otherwise
 * the work goes to the main line.
 */
class TargetBranchPolicy
{
	public function decide(TargetFactsObject $targetFactsObject): TargetSourceEnum
	{
		return match (true) {
			$targetFactsObject->pullRequestTarget !== null => TargetSourceEnum::PULL_REQUEST,
			$targetFactsObject->chosenTarget !== null => TargetSourceEnum::CHOSEN,
			default => TargetSourceEnum::MAIN_LINE,
		};
	}
}
