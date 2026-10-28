<?php

declare(strict_types=1);

namespace Src\Fetch\Objects;

use Carbon\CarbonImmutable;
use Src\Fetch\Enums\FetchStatusEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * One round along the sources, with what each source yielded.
 */
class FetchRunEntity extends ValueObject
{
	public function __construct(
		public readonly FetchRunId $fetchRunId,
		public readonly FetchStatusEnum $status,
		public readonly ?string $source,
		public readonly array $results,
		public readonly ?string $message,
		public readonly ?CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
	) {
		parent::__construct();
	}

	public function isRunning(): bool
	{
		return $this->status === FetchStatusEnum::RUNNING;
	}

	public function hasFailed(): bool
	{
		return $this->status === FetchStatusEnum::FAILED;
	}

	/**
	 * What every source yielded, as 'projects: 37' and so on.
	 */
	public function resultLine(): string
	{
		return collect($this->results)
			->map(fn (int|string $count, string $source) => $source . ': ' . match ($count) {
				'skipped' => __('overgeslagen'),
				'failed' => __('mislukt'),
				default => $count,
			})
			->implode(' · ');
	}

	public function seconds(): ?int
	{
		if ($this->startedAt === null) {
			return null;
		}

		$until = $this->finishedAt ?? CarbonImmutable::now();

		return (int) $until->diffInSeconds($this->startedAt, absolute: true);
	}

	/**
	 * A round that has run longer than this has hung; a new one may start then.
	 */
	public function startedWithin(int $minutes): bool
	{
		return $this->startedAt?->isAfter(CarbonImmutable::now()->subMinutes($minutes)) ?? false;
	}
}
