<?php

declare(strict_types=1);

namespace Src\Board\Policies;

use Src\Board\Enums\CleanUpEnum;
use Src\Board\Objects\CleanUpFactsObject;

/**
 * The rule for cleaning up a task: it goes when nothing is lost, or when
 * losing what is there was accepted.
 */
class CleanUpPolicy
{
	public function decide(CleanUpFactsObject $cleanUpFactsObject): CleanUpEnum
	{
		$hasWork = $cleanUpFactsObject->uncommittedFiles > 0 || $cleanUpFactsObject->unpushedCommits > 0;

		return $hasWork && ! $cleanUpFactsObject->discardsWork ? CleanUpEnum::WORK_AT_STAKE : CleanUpEnum::MAY_CLEAN_UP;
	}
}
