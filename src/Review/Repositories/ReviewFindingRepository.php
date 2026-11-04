<?php

declare(strict_types=1);

namespace Src\Review\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Review\Contracts\ReviewFindingRepositoryContract;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Models\ReviewFinding as ReviewFindingEloquent;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\ReviewFindingId;
use Src\Review\Objects\ReviewRoundId;

class ReviewFindingRepository implements ReviewFindingRepositoryContract
{
	public function getForCard(string $card): array
	{
		return ReviewFindingEloquent::query()
			->with(['lens', 'firstReviewRound', 'resolvedReviewRound'])
			->where('card', $card)
			->orderBy('number')
			->get()
			// open ones first, the rest keeps the order of its number
			->sortBy(fn (ReviewFindingEloquent $reviewFindingEloquent): int => $reviewFindingEloquent->status === FindingStatusEnum::OPEN->value ? 0 : 1)
			->values()
			->map(fn (ReviewFindingEloquent $reviewFindingEloquent): ReviewFindingEntity => $this->mapToEntity($reviewFindingEloquent))
			->all();
	}

	public function getForLens(string $card, LensId $lensId): array
	{
		return ReviewFindingEloquent::query()
			->with(['lens', 'firstReviewRound', 'resolvedReviewRound'])
			->where('card', $card)
			->where('lens_id', $lensId->id)
			->orderBy('number')
			->get()
			->map(fn (ReviewFindingEloquent $reviewFindingEloquent): ReviewFindingEntity => $this->mapToEntity($reviewFindingEloquent))
			->all();
	}

	public function getById(ReviewFindingId $reviewFindingId): ?ReviewFindingEntity
	{
		$reviewFindingEloquent = ReviewFindingEloquent::query()->with(['lens', 'firstReviewRound', 'resolvedReviewRound'])->find($reviewFindingId->id);

		return $reviewFindingEloquent === null ? null : $this->mapToEntity($reviewFindingEloquent);
	}

	public function add(string $card, LensId $lensId, ReviewRoundId $reviewRoundId, ?string $location, string $text): ReviewFindingEntity
	{
		// the lenses of a round write their findings at the same moment; the number is taken inside one transaction
		$reviewFindingEloquent = DB::transaction(fn (): ReviewFindingEloquent => ReviewFindingEloquent::query()->create([
			'card' => $card,
			'lens_id' => $lensId->id,
			'number' => (int) ReviewFindingEloquent::query()->where('card', $card)->lockForUpdate()->max('number') + 1,
			'location' => $location,
			'text' => $text,
			'status' => FindingStatusEnum::OPEN->value,
			'first_review_round_id' => $reviewRoundId->id,
			'last_review_round_id' => $reviewRoundId->id,
		]));

		return $this->mapToEntity($reviewFindingEloquent->load(['lens', 'firstReviewRound', 'resolvedReviewRound']));
	}

	public function markSeen(ReviewFindingId $reviewFindingId, ReviewRoundId $reviewRoundId, FindingStatusEnum $status): void
	{
		ReviewFindingEloquent::query()->whereKey($reviewFindingId->id)->update([
			'status' => $status->value,
			'last_review_round_id' => $reviewRoundId->id,
			'resolved_review_round_id' => $status === FindingStatusEnum::RESOLVED ? $reviewRoundId->id : null,
		]);
	}

	public function setStatus(ReviewFindingId $reviewFindingId, FindingStatusEnum $status, ?string $reason): ReviewFindingEntity
	{
		$reviewFindingEloquent = ReviewFindingEloquent::query()->with(['lens', 'firstReviewRound', 'resolvedReviewRound'])->findOrFail($reviewFindingId->id);

		$reviewFindingEloquent->update([
			'status' => $status->value,
			'reason' => $reason,
			// a status you set is yours, so no round of a lens is named for it
			'resolved_review_round_id' => null,
		]);

		$reviewFindingEloquent->setRelation('resolvedReviewRound', null);

		return $this->mapToEntity($reviewFindingEloquent);
	}

	private function mapToEntity(ReviewFindingEloquent $reviewFindingEloquent): ReviewFindingEntity
	{
		return new ReviewFindingEntity(
			reviewFindingId: new ReviewFindingId(id: $reviewFindingEloquent->id),
			card: (string) $reviewFindingEloquent->card,
			lensId: new LensId(id: (int) $reviewFindingEloquent->lens_id),
			lensName: (string) $reviewFindingEloquent->lens?->name,
			number: (int) $reviewFindingEloquent->number,
			location: $reviewFindingEloquent->location,
			text: (string) $reviewFindingEloquent->text,
			status: FindingStatusEnum::from((string) $reviewFindingEloquent->status),
			reason: $reviewFindingEloquent->reason,
			firstReviewRoundId: new ReviewRoundId(id: (int) $reviewFindingEloquent->first_review_round_id),
			firstRoundNumber: (int) $reviewFindingEloquent->firstReviewRound?->number,
			lastReviewRoundId: new ReviewRoundId(id: (int) $reviewFindingEloquent->last_review_round_id),
			resolvedReviewRoundId: $reviewFindingEloquent->resolved_review_round_id === null ? null : new ReviewRoundId(id: (int) $reviewFindingEloquent->resolved_review_round_id),
			resolvedRoundNumber: $reviewFindingEloquent->resolvedReviewRound?->number,
		);
	}
}
