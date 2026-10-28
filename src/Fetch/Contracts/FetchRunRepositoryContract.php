<?php

declare(strict_types=1);

namespace Src\Fetch\Contracts;

use Src\Fetch\Enums\FetchSourceEnum;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Fetch\Objects\FetchRunId;

interface FetchRunRepositoryContract
{
	public function create(): FetchRunEntity;

	public function getById(FetchRunId $fetchRunId): ?FetchRunEntity;

	public function getLatest(): ?FetchRunEntity;

	public function getRunning(): ?FetchRunEntity;

	/**
	 * Sets which source is up at this moment.
	 */
	public function markSource(FetchRunId $fetchRunId, FetchSourceEnum $source): void;

	/**
	 * @param  array<string, int|string>  $results
	 */
	public function markFinished(FetchRunId $fetchRunId, array $results, ?string $message): FetchRunEntity;
}
