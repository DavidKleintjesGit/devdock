<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;

/**
 * One pass of the lenses over a delivery. A round holds for the commit it ran
 * on, and the next one reads this one's report to tick the points off.
 */
class ReviewRoundEntity extends ValueObject
{
	/**
	 * @param  array<int, ReviewResultEntity>  $results
	 */
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
		public readonly string $card,
		public readonly int $number,
		public readonly int $seriesStart,
		public readonly ?string $commitSha,
		public readonly ?string $fingerprint,
		public readonly ?string $directory,
		public readonly RoundStatusEnum $status,
		public readonly array $results,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	/**
	 * How many lenses fell over instead of judging. What they would have found
	 * is not in the count of findings.
	 */
	public function fallenLenses(): int
	{
		return count(array_filter(
			$this->results,
			fn (ReviewResultEntity $resultEntity) => $resultEntity->verdict === VerdictEnum::ERROR,
		));
	}

	/**
	 * The findings in a few words, with the lenses that fell over named apart.
	 * Empty when there is nothing to say.
	 */
	public function findingsSummary(): string
	{
		return collect([
			$this->openFindings() === 0 ? null : trans_choice(':count bevinding|:count bevindingen', $this->openFindings(), ['count' => $this->openFindings()]),
			$this->fallenLenses() === 0 ? null : trans_choice(':count lens viel om|:count lenzen vielen om', $this->fallenLenses(), ['count' => $this->fallenLenses()]),
		])->filter()->implode(' · ');
	}

	/**
	 * Which round of its loop this is. Restarting the loop begins a new series,
	 * so the maximum counts from there and not from the first round ever.
	 */
	public function positionInSeries(): int
	{
		return $this->number - $this->seriesStart + 1;
	}

	/**
	 * Whether the work changed after this round judged it, so its verdict no
	 * longer holds.
	 */
	public function isExpiredBy(?string $fingerprint): bool
	{
		return ! $this->isRunning()
			&& $this->fingerprint !== null
			&& $fingerprint !== null
			&& $this->fingerprint !== $fingerprint;
	}

	public function isRunning(): bool
	{
		return $this->status === RoundStatusEnum::RUNNING;
	}

	/**
	 * Whether a lens of this round has no verdict yet.
	 */
	public function hasLensesAtWork(): bool
	{
		foreach ($this->results as $resultEntity) {
			if (! $resultEntity->verdict->isSettled()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every point the lenses left open in this round.
	 */
	public function openFindings(): int
	{
		return array_sum(array_map(
			fn (ReviewResultEntity $resultEntity) => $resultEntity->verdict === VerdictEnum::FAIL
				? $resultEntity->findings
				: 0,
			$this->results,
		));
	}

	/**
	 * A round is clean when every lens that was called came back PASS. A lens
	 * that was skipped had nothing to judge and does not hold it up.
	 */
	public function isClean(): bool
	{
		foreach ($this->results as $resultEntity) {
			if (! in_array($resultEntity->verdict, [VerdictEnum::PASS, VerdictEnum::SKIPPED], true)) {
				return false;
			}
		}

		return $this->results !== [];
	}

	/**
	 * The lenses that were actually called, skipped ones left out.
	 *
	 * @return array<int, ReviewResultEntity>
	 */
	public function called(): array
	{
		return array_values(array_filter(
			$this->results,
			fn (ReviewResultEntity $resultEntity) => $resultEntity->verdict !== VerdictEnum::SKIPPED,
		));
	}
}
