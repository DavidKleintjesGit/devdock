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
		public readonly ?string $commitSha,
		public readonly ?string $directory,
		public readonly RoundStatusEnum $status,
		public readonly array $results,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	public function isRunning(): bool
	{
		return $this->status === RoundStatusEnum::RUNNING;
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
