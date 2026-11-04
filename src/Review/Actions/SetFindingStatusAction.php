<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewFindingRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensFindingsFactsObject;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\ReviewFindingId;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Policies\LensVerdictPolicy;

/**
 * Sets a finding aside with your reason, or opens it again. The lens of that
 * finding is judged again in the last round of the card, so a round that only
 * failed on findings you set aside comes out clean, and the loop stops
 * waiting on them.
 *
 * @method static ?ReviewFindingEntity execute(ReviewFindingId $reviewFindingId, FindingStatusEnum $status, ?string $reason = null)
 */
class SetFindingStatusAction extends Command
{
	public function __construct(
		public readonly ReviewFindingId $reviewFindingId,
		public readonly FindingStatusEnum $status,
		public readonly ?string $reason = null,
	) {}

	public function handle(
		ReviewFindingRepositoryContract $reviewFindingRepository,
		ReviewRoundRepositoryContract $reviewRoundRepository,
	): ?ReviewFindingEntity {
		$reviewFindingEntity = $reviewFindingRepository->getById($this->reviewFindingId);

		if ($reviewFindingEntity === null) {
			return null;
		}

		$reviewFindingEntity = $reviewFindingRepository->setStatus(
			$this->reviewFindingId,
			$this->status,
			$this->status === FindingStatusEnum::IGNORED ? $this->reason : null,
		);

		$latestRound = $reviewRoundRepository->getLatestForCard($reviewFindingEntity->card);
		$resultEntity = collect($latestRound?->results ?? [])
			->first(fn (ReviewResultEntity $resultEntity): bool => $resultEntity->lensId->id === $reviewFindingEntity->lensId->id);

		// only a lens that judged in a finished round is judged again; one that is still at work or fell over keeps its state
		if ($latestRound === null || $latestRound->isRunning() || ! in_array($resultEntity?->verdict, [VerdictEnum::PASS, VerdictEnum::FAIL], true)) {
			return $reviewFindingEntity;
		}

		$openFindings = count(array_filter(
			$reviewFindingRepository->getForLens($reviewFindingEntity->card, $reviewFindingEntity->lensId),
			fn (ReviewFindingEntity $finding): bool => $finding->isOpen(),
		));

		$reviewRoundRepository->rejudge(
			$latestRound->reviewRoundId,
			$reviewFindingEntity->lensId,
			resolve(LensVerdictPolicy::class)->decide(new LensFindingsFactsObject(isReadable: true, openFindings: $openFindings)),
			$openFindings,
		);

		return $reviewFindingEntity;
	}
}
