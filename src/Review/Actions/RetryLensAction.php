<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Facades\Bus;
use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\LensProcessRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;

/**
 * Sends one lens out again. Only a lens that fell over is worth repeating, so
 * the verdicts of the others stay exactly as they were and the round reopens
 * for this one alone.
 *
 * @method static ?ReviewRoundEntity execute(ReviewRoundId $reviewRoundId, LensId $lensId)
 */
class RetryLensAction extends Command
{
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
		public readonly LensId $lensId,
	) {}

	public function handle(
		ReviewRoundRepositoryContract $reviewRoundRepository,
		LensProcessRepositoryContract $lensProcessRepository,
	): ?ReviewRoundEntity {
		$reviewRoundEntity = $reviewRoundRepository->getById($this->reviewRoundId);

		if ($reviewRoundEntity === null || ! $this->fellOver($reviewRoundEntity)) {
			return $reviewRoundEntity;
		}

		$reviewRoundRepository->reopen($this->reviewRoundId);

		$roundId = (int) $this->reviewRoundId->id;

		Bus::batch([new RunLensAction($this->reviewRoundId, $this->lensId)])
			->name(__('lens opnieuw'))
			->onQueue('lenses')
			->finally(fn () => SettleRoundAction::execute(new ReviewRoundId(id: $roundId)))
			->dispatch();

		$lensProcessRepository->startWorkers(1);

		return $reviewRoundRepository->getById($this->reviewRoundId);
	}

	private function fellOver(ReviewRoundEntity $reviewRoundEntity): bool
	{
		foreach ($reviewRoundEntity->results as $resultEntity) {
			if ($resultEntity->lensId->id === $this->lensId->id) {
				return $resultEntity->verdict->canBeRetried();
			}
		}

		return false;
	}
}
