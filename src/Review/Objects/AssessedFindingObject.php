<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\FindingChoiceEnum;

/**
 * One open finding of a round with what the agent made of it, when it said
 * something about it.
 */
class AssessedFindingObject extends ValueObject
{
	public function __construct(
		public readonly ReviewFindingEntity $finding,
		public readonly ?AssessmentObject $assessment,
	) {
		parent::__construct();
	}

	/**
	 * The choice the list starts on: what the agent's verdict suggests, a fix without one.
	 */
	public function suggestedChoice(): FindingChoiceEnum
	{
		return $this->assessment?->verdict->suggestedChoice() ?? FindingChoiceEnum::FIX;
	}
}
