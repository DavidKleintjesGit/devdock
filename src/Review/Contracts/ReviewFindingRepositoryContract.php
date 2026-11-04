<?php

declare(strict_types=1);

namespace Src\Review\Contracts;

use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\ReviewFindingId;
use Src\Review\Objects\ReviewRoundId;

interface ReviewFindingRepositoryContract
{
	/**
	 * Every finding of a card, open ones first, then by number.
	 *
	 * @return list<ReviewFindingEntity>
	 */
	public function getForCard(string $card): array;

	/**
	 * The findings one lens made on a card.
	 *
	 * @return list<ReviewFindingEntity>
	 */
	public function getForLens(string $card, LensId $lensId): array;

	public function getById(ReviewFindingId $reviewFindingId): ?ReviewFindingEntity;

	/**
	 * Writes down a new finding under the next free number of its card.
	 */
	public function add(string $card, LensId $lensId, ReviewRoundId $reviewRoundId, ?string $location, string $text): ReviewFindingEntity;

	/**
	 * Notes that a lens looked at a finding again in this round, and where it
	 * left it; a resolved one remembers the round that saw it fixed.
	 */
	public function markSeen(ReviewFindingId $reviewFindingId, ReviewRoundId $reviewRoundId, FindingStatusEnum $status): void;

	/**
	 * Sets a finding to a status by hand, with the reason that came with it.
	 */
	public function setStatus(ReviewFindingId $reviewFindingId, FindingStatusEnum $status, ?string $reason): ReviewFindingEntity;
}
