<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;

/**
 * Closes a round once every lens has spoken. Runs after the last job of the
 * round, whether it went well or not: a lens that never wrote anything down
 * is put on vastgelopen, so the round never waits on something that is gone.
 *
 * @method static ?ReviewRoundEntity execute(ReviewRoundId $reviewRoundId)
 */
class SettleRoundAction extends Command
{
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
	) {}

	public function handle(ReviewRoundRepositoryContract $reviewRoundRepository): ?ReviewRoundEntity
	{
		$reviewRoundEntity = $reviewRoundRepository->getById($this->reviewRoundId);

		if ($reviewRoundEntity === null) {
			return null;
		}

		foreach ($reviewRoundEntity->results as $resultEntity) {
			if ($resultEntity->verdict->isSettled()) {
				continue;
			}

			// a lens that is still sending signs of life is not gone, whatever
			// ended around it: it writes its own verdict when it is done, and
			// that closes the round
			if ($resultEntity->verdict === VerdictEnum::RUNNING && ! $resultEntity->hasGoneQuiet()) {
				continue;
			}

			$reviewRoundRepository->writeResult(
				$this->reviewRoundId,
				$resultEntity->lensId,
				VerdictEnum::ERROR,
				0,
				__('Deze lens heeft niets teruggegeven. Zet hem opnieuw uit om het nog eens te proberen.'),
			);
		}

		$settled = $reviewRoundRepository->settle($this->reviewRoundId);

		// the verdicts exist only now, and the agent reads them from files of
		// their own rather than from something somebody has to paste in
		if (! $settled->isRunning()) {
			WriteRoundReportsAction::execute($settled);

			// een ronde die niet schoon terugkwam gaat vanzelf door naar de
			// agent; pas bij de laatste ronde komt er een mens aan te pas
			ContinueReviewAction::execute($settled);
		}

		return $settled;
	}
}
