<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A round that waits on your choice: its open findings, each with what the
 * agent made of it when it assessed them.
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

	/**
	 * The heading of the round in the chat: an assessment when the agent assessed it, otherwise its findings.
	 */
	public function heading(): string
	{
		return collect($this->findings)->contains(fn (AssessedFindingObject $assessedFindingObject): bool => $assessedFindingObject->assessment !== null)
			? __('Beoordeling ronde :number · jij kiest', ['number' => $this->roundNumber])
			: trans_choice('Ronde :number · :count bevinding · jij kiest|Ronde :number · :count bevindingen · jij kiest', count($this->findings), ['number' => $this->roundNumber, 'count' => count($this->findings)]);
	}
}
