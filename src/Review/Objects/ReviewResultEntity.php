<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\VerdictEnum;

/**
 * What one lens made of one round: its verdict, and the report it wrote with
 * the verdict on the first line. While it works it also carries what it is
 * doing at that moment, so a round shows where it stands instead of only that
 * something is happening.
 */
class ReviewResultEntity extends ValueObject
{
	public function __construct(
		public readonly LensId $lensId,
		public readonly string $lensName,
		public readonly VerdictEnum $verdict,
		public readonly int $findings,
		public readonly ?string $report,
		public readonly int $touchedFiles,
		public readonly ?string $sessionUuid,
		public readonly ?string $activity,
		public readonly int $toolCalls,
		public readonly ?float $cost,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $lastSeenAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	/**
	 * The line under the lens name: what it is doing, what it found, or why
	 * it did nothing.
	 */
	public function summary(): string
	{
		return match ($this->verdict) {
			VerdictEnum::SKIPPED => __('geen gewijzigd bestand op zijn paden'),
			VerdictEnum::WAITING => __('wacht op zijn beurt'),
			VerdictEnum::RUNNING => $this->activity ?? trans_choice(
				':count bestand geraakt|:count bestanden geraakt',
				$this->touchedFiles,
				['count' => $this->touchedFiles],
			),
			VerdictEnum::PASS => __('niets gevonden'),
			VerdictEnum::FAIL => trans_choice(
				':count bevinding|:count bevindingen',
				$this->findings,
				['count' => $this->findings],
			),
			VerdictEnum::ERROR => $this->reason(),
		};
	}

	/**
	 * The numbers beside a lens that is working: how long it has been at it,
	 * how much it reached for, and when it last said anything.
	 */
	public function progressLabel(): ?string
	{
		if ($this->verdict !== VerdictEnum::RUNNING || $this->startedAt === null) {
			return null;
		}

		$parts = [__(':count seconden bezig', ['count' => $this->startedAt->diffInSeconds(CarbonImmutable::now())])];

		if ($this->toolCalls > 0) {
			$parts[] = trans_choice(':count tool call|:count tool calls', $this->toolCalls, ['count' => $this->toolCalls]);
		}

		if ($this->lastSeenAt !== null) {
			$parts[] = __('laatste teken :count s geleden', [
				'count' => $this->lastSeenAt->diffInSeconds(CarbonImmutable::now()),
			]);
		}

		return implode(' · ', $parts);
	}

	/**
	 * Whether this lens has gone quiet for longer than a working lens ever
	 * does, which is what tells a slow one from a dead one.
	 */
	public function hasGoneQuiet(): bool
	{
		if ($this->verdict !== VerdictEnum::RUNNING || $this->lastSeenAt === null) {
			return false;
		}

		return $this->lastSeenAt->diffInSeconds(CarbonImmutable::now()) > (int) config('devdock.review.lens_idle');
	}

	/**
	 * Why it fell over, in the words it left behind.
	 */
	private function reason(): string
	{
		$first = trim(strtok((string) $this->report, "\n") ?: '');

		return $first === '' ? __('vastgelopen zonder bericht') : $first;
	}
}
