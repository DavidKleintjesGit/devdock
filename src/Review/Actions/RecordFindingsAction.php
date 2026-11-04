<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewFindingRepositoryContract;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\VerdictObject;

/**
 * Writes down what a lens found in a round: what became of its earlier
 * findings by their number, and its new ones under new numbers. A finding you
 * ignored stays ignored, whatever the lens says about it; one the lens did not
 * mention stays as it was. Returns how many of its findings on the card are
 * still open.
 *
 * @method static int execute(string $card, ReviewRoundId $reviewRoundId, LensId $lensId, VerdictObject $verdictObject)
 */
class RecordFindingsAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ReviewRoundId $reviewRoundId,
		public readonly LensId $lensId,
		public readonly VerdictObject $verdictObject,
	) {}

	public function handle(ReviewFindingRepositoryContract $reviewFindingRepository): int
	{
		$earlier = collect($reviewFindingRepository->getForLens($this->card, $this->lensId))
			->keyBy(fn (ReviewFindingEntity $reviewFindingEntity): int => $reviewFindingEntity->number);

		foreach ($this->verdictObject->earlierFindings as $number => $status) {
			$reviewFindingEntity = $earlier->get($number);

			if ($reviewFindingEntity !== null && $reviewFindingEntity->status !== FindingStatusEnum::IGNORED) {
				$reviewFindingRepository->markSeen($reviewFindingEntity->reviewFindingId, $this->reviewRoundId, $status);
			}
		}

		// PASS means the lens found everything of its own fixed; only a finding it still calls OPEN stays
		if ($this->verdictObject->verdict === VerdictEnum::PASS) {
			foreach ($earlier as $number => $reviewFindingEntity) {
				if ($reviewFindingEntity->isOpen() && ($this->verdictObject->earlierFindings[$number] ?? null) !== FindingStatusEnum::OPEN) {
					$reviewFindingRepository->markSeen($reviewFindingEntity->reviewFindingId, $this->reviewRoundId, FindingStatusEnum::RESOLVED);
				}
			}
		}

		foreach ($this->verdictObject->newFindings as $newFindingObject) {
			$reviewFindingRepository->add($this->card, $this->lensId, $this->reviewRoundId, $newFindingObject->location, $newFindingObject->text);
		}

		return count(array_filter(
			$reviewFindingRepository->getForLens($this->card, $this->lensId),
			fn (ReviewFindingEntity $reviewFindingEntity): bool => $reviewFindingEntity->isOpen(),
		));
	}
}
