<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;

/**
 * Closes the round once every lens has a verdict and hands what was found to
 * the agent. Every lens calls this when it is done, so the loop does not hang
 * on one callback at the end of a batch; whichever lens comes last closes it.
 *
 * @method static ?ReviewRoundEntity execute(ReviewRoundId $reviewRoundId)
 */
class CloseRoundAction extends Command
{
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
	) {}

	public function handle(ReviewRoundRepositoryContract $reviewRoundRepository): ?ReviewRoundEntity
	{
		if ($reviewRoundRepository->getById($this->reviewRoundId) === null) {
			return null;
		}

		$settled = $reviewRoundRepository->settle($this->reviewRoundId);

		if ($settled->isRunning()) {
			return $settled;
		}

		// the verdicts exist only now, and the agent reads them from files of
		// their own rather than from something somebody has to paste in
		WriteRoundReportsAction::execute($settled);

		ContinueReviewAction::execute($settled);

		return $settled;
	}
}
