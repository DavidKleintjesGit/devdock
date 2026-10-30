<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Enums\PromptSourceEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\DescribeReportAction;
use Src\PullRequest\Actions\BuildReviewPromptAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Review\Actions\BuildRoundPromptAction;
use Src\Review\Actions\GetRoundsAction;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;

/**
 * A first prompt for a task, built from whatever it already carries: the
 * ticket it came from, the note you left on it, or the review remarks waiting
 * on its pull requests.
 *
 * @method static ?string execute(string $card, PromptSourceEnum $source)
 */
class BuildTaskPromptAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly PromptSourceEnum $source,
	) {}

	public function handle(): ?string
	{
		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->card);

		if ($cardObject === null) {
			return null;
		}

		return match ($this->source) {
			PromptSourceEnum::TICKET => $this->fromTicket($cardObject),
			PromptSourceEnum::NOTE => $this->fromNote($cardObject),
			PromptSourceEnum::REVIEW => $this->fromReview($cardObject),
			PromptSourceEnum::ROUND => $this->fromRound($cardObject),
		};
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
			$this->howToGoAbout($cardObject),
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
			$this->howToGoAbout($cardObject),
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

	/**
	 * The ground rules every first prompt carries: look before you change, and
	 * nothing leaves the machine unasked.
	 */
	private function howToGoAbout(CardObject $cardObject): string
	{
		$lines = [__('Werkwijze:')];

		if ($cardObject->branch !== null) {
			$lines[] = __('- Je werkt op branch :branch.', ['branch' => $cardObject->branch]);
		}

		return implode("\n", [
			...$lines,
			__('- Lees eerst de code die hierbij hoort en leg me een plan voor voordat je iets aanpast.'),
			__('- Volg de regels uit CLAUDE.md en draai de tests van wat je aanraakt.'),
			__('- Commit niet, push niet en plaats nergens een reactie zonder dat ik daar eerst ja op heb gezegd.'),
			DescribeReportAction::execute(),
		]);
	}
}
