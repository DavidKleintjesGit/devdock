<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Enums\PromptSourceEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\PullRequest\Actions\BuildReviewPromptAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Review\Actions\BuildRoundPromptAction;
use Src\Review\Actions\GetRoundsAction;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;

/**
 * A prompt for a task, built from the parts of its context you picked: the
 * ticket it came from, the note you left on it, the review remarks waiting on
 * its pull requests, or the findings of the last round.
 *
 * @method static ?string execute(string $card, array $sources)
 */
class BuildTaskPromptAction extends Command
{
	public function __construct(
		public readonly string $card,
		/** @var array<int, PromptSourceEnum> */
		public readonly array $sources,
	) {}

	public function handle(): ?string
	{
		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->card);

		if ($cardObject === null) {
			return null;
		}

		$parts = array_filter(array_map(fn (PromptSourceEnum $source) => match ($source) {
			PromptSourceEnum::TICKET => $this->fromTicket($cardObject),
			PromptSourceEnum::NOTE => $this->fromNote($cardObject),
			PromptSourceEnum::REVIEW => $this->fromReview($cardObject),
			PromptSourceEnum::ROUND => $this->fromRound($cardObject),
		}, $this->sources));

		if ($parts === []) {
			return null;
		}

		return implode("\n\n", $parts);
	}

	/**
	 * De punten van de laatste ronde, als die er is.
	 */
	private function fromRound(CardObject $cardObject): ?string
	{
		$reviewRoundEntity = collect(GetRoundsAction::execute($cardObject->id))->last();

		return $reviewRoundEntity === null ? null : BuildRoundPromptAction::execute($reviewRoundEntity);
	}

	private function fromTicket(CardObject $cardObject): ?string
	{
		$ticketEntity = collect(GetTicketsAction::execute())
			->first(fn (TicketEntity $ticketEntity) => $ticketEntity->key === $cardObject->ticketKey);

		if ($ticketEntity === null) {
			return null;
		}

		$opening = $ticketEntity->key . ' — ' . $ticketEntity->summary;

		return implode("\n\n", array_filter([
			$opening,
			$ticketEntity->description,
		]));
	}

	private function fromNote(CardObject $cardObject): ?string
	{
		if (blank($cardObject->description)) {
			return null;
		}

		return implode("\n\n", [
			$cardObject->title,
			$cardObject->description,
		]);
	}

	private function fromReview(CardObject $cardObject): ?string
	{
		$pullRequestIds = $cardObject->pullRequests
			->filter(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested())
			->map(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->pullRequestId)
			->values()
			->all();

		return $pullRequestIds === [] ? null : BuildReviewPromptAction::execute($pullRequestIds);
	}
}
