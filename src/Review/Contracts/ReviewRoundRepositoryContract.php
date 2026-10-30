<?php

declare(strict_types=1);

namespace Src\Review\Contracts;

use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundEntityCollection;
use Src\Review\Objects\ReviewRoundId;

interface ReviewRoundRepositoryContract
{
	/**
	 * Every round of one task, oldest first.
	 */
	public function getForCard(string $card): ReviewRoundEntityCollection;

	/**
	 * De laatste ronde van elke taak, op kaartsleutel. Het bord heeft ze
	 * allemaal tegelijk nodig om te weten waar een kaart staat.
	 *
	 * @return array<string, ReviewRoundEntity>
	 */
	public function getLatestPerCard(): array;

	public function getById(ReviewRoundId $reviewRoundId): ?ReviewRoundEntity;

	/**
	 * Every round that is still running, across all tasks.
	 */
	public function getRunning(): ReviewRoundEntityCollection;

	/**
	 * Opens the next round of a task and puts every lens on it: the ones that
	 * were called wait their turn, the rest are skipped straight away.
	 *
	 * @param  array<int, array{lens_id: LensId, verdict: VerdictEnum, touched_files: int}>  $lenses
	 */
	public function open(string $card, ?string $commitSha, ?string $directory, array $lenses): ReviewRoundEntity;

	public function markResultRunning(ReviewRoundId $reviewRoundId, LensId $lensId, ?string $sessionUuid): void;

	/**
	 * Writes down where a lens stands while it works. Every call is also a
	 * sign of life, which is what tells a slow lens from a dead one.
	 */
	public function markResultProgress(
		ReviewRoundId $reviewRoundId,
		LensId $lensId,
		?string $activity,
		int $toolCalls,
		?float $cost,
		?string $sessionUuid,
	): void;

	public function writeResult(
		ReviewRoundId $reviewRoundId,
		LensId $lensId,
		VerdictEnum $verdict,
		int $findings,
		?string $report,
	): ReviewRoundEntity;

	/**
	 * Closes the round once no lens is still working.
	 */
	public function settle(ReviewRoundId $reviewRoundId): ReviewRoundEntity;

	/**
	 * Puts a round back on running, for a lens that is being sent out again.
	 */
	public function reopen(ReviewRoundId $reviewRoundId): ReviewRoundEntity;

	public function stop(ReviewRoundId $reviewRoundId): ReviewRoundEntity;

	/**
	 * Takes every round of a task off, for when the task itself goes.
	 */
	public function deleteForCard(string $card): void;
}
