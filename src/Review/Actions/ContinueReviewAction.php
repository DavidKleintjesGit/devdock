<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunObject;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Zet de lus door na een ronde die niet schoon terugkwam: de agent krijgt de
 * punten en werkt ze bij, zonder dat daar eerst iets voor gevraagd hoeft te
 * worden. Dat is waar rondes voor zijn.
 *
 * Bij de laatste ronde houdt het op. Dan is er iets aan de hand dat een mens
 * moet bekijken, en daar helpt nog een beurt van dezelfde agent niet bij.
 *
 * @method static ?PromptRunEntity execute(ReviewRoundEntity $reviewRoundEntity)
 */
class ContinueReviewAction extends Command
{
	public function __construct(
		public readonly ReviewRoundEntity $reviewRoundEntity,
	) {}

	public function handle(): ?PromptRunEntity
	{
		if (! $this->keepsGoing()) {
			return null;
		}

		$prompt = BuildRoundPromptAction::execute($this->reviewRoundEntity);

		if ($prompt === null) {
			return null;
		}

		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->reviewRoundEntity->card);

		if ($cardObject?->branch === null) {
			return null;
		}

		return StartPromptAction::execute(PromptRunObject::from([
			'card' => $cardObject->id,
			'review_round_id' => $this->reviewRoundEntity->reviewRoundId->id,
			'project' => $cardObject->project,
			'branch' => $cardObject->branch,
			'ticket_key' => $cardObject->ticketKey,
			'prompt' => $prompt,
		]));
	}

	private function keepsGoing(): bool
	{
		return $this->reviewRoundEntity->status === RoundStatusEnum::FAILED
			&& $this->reviewRoundEntity->openFindings() > 0
			&& $this->reviewRoundEntity->number < (int) config('devdock.review.max_rounds');
	}
}
