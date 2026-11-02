<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Every lens that found something, gathered into one prompt. The agent gets
 * the whole round at once instead of one report at a time, so it can weigh
 * the findings against each other before it changes anything.
 *
 * The findings stand in the report files of the round and the prompt points
 * at them, so the chat stays readable and there is one source. Only a report
 * that could not be written goes into the prompt itself.
 *
 * @method static ?string execute(ReviewRoundEntity $reviewRoundEntity)
 */
class BuildRoundPromptAction extends Command
{
	public function __construct(
		public readonly ReviewRoundEntity $reviewRoundEntity,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): ?string
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

		return implode("\n\n", [
			$this->opening($reviewRoundEntity, $failed),
			$this->findings($agentContextRepository, $failed),
			$this->howToGoAbout(),
		]);
	}

	/**
	 * @param  array<int, ReviewResultEntity>  $failed
	 */
	private function opening(ReviewRoundEntity $reviewRoundEntity, array $failed): string
	{
		$names = implode(', ', array_map(
			fn (ReviewResultEntity $resultEntity) => $resultEntity->lensName,
			$failed,
		));

		$findingCount = array_sum(array_map(
			fn (ReviewResultEntity $resultEntity) => $resultEntity->findings,
			$failed,
		));

		return __('Ronde :number kwam niet schoon terug. :names vonden samen :count bevindingen op commit :commit.', [
			'number' => $reviewRoundEntity->number,
			'names' => $names,
			'count' => $findingCount,
			'commit' => $reviewRoundEntity->commitSha ?? '-',
		]);
	}

	/**
	 * @param  array<int, ReviewResultEntity>  $failed
	 */
	private function findings(AgentContextRepositoryContract $agentContextRepository, array $failed): string
	{
		$reviewRoundEntity = $this->reviewRoundEntity;
		$lines = [__('De bevindingen staan per lens in een rapport; lees die eerst:')];
		$pasted = [];

		foreach ($failed as $resultEntity) {
			$path = $agentContextRepository->reportPath($reviewRoundEntity->card, $reviewRoundEntity->number, $resultEntity->lensName);

			if ($reviewRoundEntity->directory !== null && is_file($reviewRoundEntity->directory . '/' . $path)) {
				$lines[] = '- ' . trans_choice(':lens: :count bevinding in :path|:lens: :count bevindingen in :path', $resultEntity->findings, [
					'lens' => $resultEntity->lensName,
					'count' => $resultEntity->findings,
					'path' => $path,
				]);

				continue;
			}

			// the file is missing, so what it would have said goes in here
			$pasted[] = $resultEntity->lensName . "\n\n" . trim((string) $resultEntity->report);
		}

		return implode("\n\n", array_filter([
			count($lines) > 1 ? implode("\n", $lines) : null,
			...$pasted,
		]));
	}

	private function howToGoAbout(): string
	{
		return implode("\n", [
			__('Hoe een rapport is opgebouwd:'),
			__('- Een lens die al eerder keek, begint met "Eerder gevonden". RESOLVED: de lens keurde je vorige oplossing goed, daar doe je niets meer mee. OPEN: je vorige oplossing was niet genoeg, kijk er opnieuw naar.'),
			__('- Daaronder staan de nieuwe bevindingen, genummerd.'),
			__('- Wat je de ronde ervoor kreeg staat in de map van die ronde, naast deze; lees die alleen als je wilt weten wat je eerder deed.'),
			'',
			__('Werkwijze:'),
			__('- Werk alleen de bevindingen die OPEN of nieuw zijn bij, één voor één, en zeg per bevinding wat je ermee doet.'),
			__('- Is iets al opgelost, zeg dan welk commit dat deed. Vink zelf niets af.'),
			__('- Ben je het ergens niet mee eens, schrijf dan een voorstel en laat het aan mij zien.'),
			__('- Verbreed de scope niet: alleen deze bevindingen.'),
			// the loop runs without anyone watching; asking for a yes would stop it after one turn
			__('- Dit is een opdracht om uit te voeren: go ahead, implement this. Los de bevindingen direct op en wacht niet op akkoord; de volgende ronde van de lenzen controleert je werk.'),
			__('- Commit en push niet; dat doe ik zelf.'),
			// RunPromptAction reads this status to decide whether the lenses go again
			__('- Sluit af met status done als je alle bevindingen hebt opgelost: dan gaan de lenzen er opnieuw over. Leg je iets aan mij voor of kom je niet verder, gebruik dan question of blocked: dan stopt de loop tot ik antwoord.'),
		]);
	}
}
