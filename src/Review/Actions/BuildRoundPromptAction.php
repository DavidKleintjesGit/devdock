<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewFindingRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Every finding still open on the card, gathered into one prompt per lens.
 * The agent gets the whole round at once instead of one report at a time, so
 * it can weigh the findings against each other before it changes anything. A
 * finding you ignored or a lens saw fixed is left out; only a lens whose
 * answer could not be read has its report pasted in as it stands.
 *
 * @method static ?string execute(ReviewRoundEntity $reviewRoundEntity)
 */
class BuildRoundPromptAction extends Command
{
	public function __construct(
		public readonly ReviewRoundEntity $reviewRoundEntity,
	) {}

	public function handle(ReviewFindingRepositoryContract $reviewFindingRepository): ?string
	{
		$reviewRoundEntity = $this->reviewRoundEntity;

		if ($reviewRoundEntity->isRunning()) {
			return null;
		}

		$failed = array_filter(
			$reviewRoundEntity->results,
			fn (ReviewResultEntity $resultEntity) => $resultEntity->verdict === VerdictEnum::FAIL,
		);

		if ($failed === []) {
			return null;
		}

		$open = array_values(array_filter(
			$reviewFindingRepository->getForCard($reviewRoundEntity->card),
			fn (ReviewFindingEntity $reviewFindingEntity): bool => $reviewFindingEntity->isOpen(),
		));

		return implode("\n\n", array_filter([
			__('Ronde :number kwam niet schoon terug. Deze :count bevindingen staan nog open, op commit :commit:', [
				'number' => $reviewRoundEntity->positionInSeries(),
				'count' => count($open),
				'commit' => $reviewRoundEntity->commitSha ?? '-',
			]),
			$this->openFindings($open),
			$this->unreadableReports($failed, $open),
			$this->howToGoAbout(),
		]));
	}

	/**
	 * @param  list<ReviewFindingEntity>  $open
	 */
	private function openFindings(array $open): ?string
	{
		if ($open === []) {
			return null;
		}

		return collect($open)
			->groupBy(fn (ReviewFindingEntity $reviewFindingEntity): string => $reviewFindingEntity->lensName)
			->map(fn ($findings, string $lens): string => $lens . "\n" . $findings
				->map(fn (ReviewFindingEntity $reviewFindingEntity): string => '- ' . $reviewFindingEntity->line())
				->implode("\n"))
			->implode("\n\n");
	}

	/**
	 * What a lens said that could not be read into findings, pasted in whole.
	 *
	 * @param  array<int, ReviewResultEntity>  $failed
	 * @param  list<ReviewFindingEntity>  $open
	 */
	private function unreadableReports(array $failed, array $open): ?string
	{
		$lensesWithFindings = collect($open)->map(fn (ReviewFindingEntity $reviewFindingEntity): int => (int) $reviewFindingEntity->lensId->id)->all();

		$pasted = array_map(
			fn (ReviewResultEntity $resultEntity): string => $resultEntity->lensName . "\n\n" . trim((string) $resultEntity->report),
			array_filter($failed, fn (ReviewResultEntity $resultEntity): bool => ! in_array((int) $resultEntity->lensId->id, $lensesWithFindings, true)),
		);

		return $pasted === [] ? null : implode("\n\n", $pasted);
	}

	private function howToGoAbout(): string
	{
		return implode("\n", [
			__('Werkwijze:'),
			__('- Werk de open bevindingen bij, één voor één, en zeg per nummer wat je ermee doet.'),
			__('- Is iets al opgelost, zeg dan welk commit dat deed. Vink zelf niets af.'),
			__('- Ben je het ergens niet mee eens, schrijf dan een voorstel en laat het aan mij zien.'),
			__('- Verbreed de scope niet: alleen deze bevindingen.'),
			// the loop runs without anyone watching; asking for a yes would stop it after one turn
			__('- Dit is een opdracht om uit te voeren: go ahead, implement this. Los de bevindingen direct op en wacht niet op akkoord; de volgende ronde van de lenzen controleert je werk.'),
			__('- Git lees je alleen. Committen, pushen en rebasen doe ik met de knoppen in DevDock, en DevDock houdt het voor jou tegen. Laat je werk in de werkkopie staan.'),
			// RunPromptAction reads this status to decide whether the lenses go again
			__('- Sluit af met status done als je alle bevindingen hebt opgelost: dan gaan de lenzen er opnieuw over. Leg je iets aan mij voor of kom je niet verder, gebruik dan question of blocked: dan stopt de loop tot ik antwoord.'),
		]);
	}
}
