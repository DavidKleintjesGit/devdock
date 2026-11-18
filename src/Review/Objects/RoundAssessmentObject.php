<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A round that waits on your choice: its open findings, each with what the
 * agent made of it.
 */
class RoundAssessmentObject extends ValueObject
{
	/**
	 * @param  list<AssessedFindingObject>  $findings
	 */
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
		public readonly int $roundNumber,
		public readonly array $findings,
	) {
		parent::__construct();
	}
}
