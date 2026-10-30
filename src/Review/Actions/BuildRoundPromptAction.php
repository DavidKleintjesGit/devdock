<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Every lens that found something, gathered into one prompt. The agent gets
 * the whole round at once instead of one report at a time, so it can weigh
 * the points against each other before it changes anything.
 *
 * @method static ?string execute(ReviewRoundEntity $reviewRoundEntity)
 */
class BuildRoundPromptAction extends Command
{
	public function __construct(
		public readonly ReviewRoundEntity $reviewRoundEntity,
	) {}

	public function handle(): ?string
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
			$this->findings($failed),
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

		$points = array_sum(array_map(
			fn (ReviewResultEntity $resultEntity) => $resultEntity->findings,
			$failed,
		));

		return __('Ronde :number kwam niet schoon terug. :names vonden samen :count punten op commit :commit.', [
			'number' => $reviewRoundEntity->number,
			'names' => $names,
			'count' => $points,
			'commit' => $reviewRoundEntity->commitSha ?? '-',
		]);
	}

	/**
	 * @param  array<int, ReviewResultEntity>  $failed
	 */
	private function findings(array $failed): string
	{
		$blocks = [];

		foreach ($failed as $resultEntity) {
			$blocks[] = $resultEntity->lensName . "\n\n" . trim((string) $resultEntity->report);
		}

		return implode("\n\n", $blocks);
	}

	private function howToGoAbout(): string
	{
		return implode("\n", [
			__('Werkwijze:'),
			__('- Loop de punten één voor één langs en zeg per punt wat je ermee doet.'),
			__('- Is iets al opgelost, zeg dan welk commit dat deed. Vink zelf niets af.'),
			__('- Ben je het ergens niet mee eens, schrijf dan een voorstel en laat het aan mij zien.'),
			__('- Verbreed de scope niet: alleen deze punten.'),
			__('- Leg je plan eerst voor en wacht op mijn akkoord voordat je iets aanpast.'),
		]);
	}
}
