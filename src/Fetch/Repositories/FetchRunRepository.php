<?php

declare(strict_types=1);

namespace Src\Fetch\Repositories;

use Carbon\CarbonImmutable;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Enums\FetchSourceEnum;
use Src\Fetch\Enums\FetchStatusEnum;
use Src\Fetch\Models\FetchRun as FetchRunEloquent;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Fetch\Objects\FetchRunId;

class FetchRunRepository implements FetchRunRepositoryContract
{
	public function create(): FetchRunEntity
	{
		$fetchRunEloquent = FetchRunEloquent::query()->create([
			'status' => FetchStatusEnum::RUNNING->value,
			'started_at' => CarbonImmutable::now(),
			'results' => [],
		]);

		return $this->mapToEntity($fetchRunEloquent);
	}

	public function getById(FetchRunId $fetchRunId): ?FetchRunEntity
	{
		$fetchRunEloquent = FetchRunEloquent::query()->find($fetchRunId->id);

		return $fetchRunEloquent === null ? null : $this->mapToEntity($fetchRunEloquent);
	}

	public function getLatest(): ?FetchRunEntity
	{
		$fetchRunEloquent = FetchRunEloquent::query()->latest('id')->first();

		return $fetchRunEloquent === null ? null : $this->mapToEntity($fetchRunEloquent);
	}

	public function getRunning(): ?FetchRunEntity
	{
		$fetchRunEloquent = FetchRunEloquent::query()
			->where('status', FetchStatusEnum::RUNNING->value)
			->latest('id')
			->first();

		return $fetchRunEloquent === null ? null : $this->mapToEntity($fetchRunEloquent);
	}

	public function markSource(FetchRunId $fetchRunId, FetchSourceEnum $source): void
	{
		FetchRunEloquent::query()->whereKey($fetchRunId->id)->update(['source' => $source->value]);
	}

	public function markFinished(FetchRunId $fetchRunId, array $results, ?string $message): FetchRunEntity
	{
		$fetchRunEloquent = FetchRunEloquent::query()->findOrFail($fetchRunId->id);

		$fetchRunEloquent->update([
			'status' => FetchStatusEnum::DONE->value,
			'source' => null,
			'results' => $results,
			'message' => $message,
			'finished_at' => CarbonImmutable::now(),
		]);

		return $this->mapToEntity($fetchRunEloquent);
	}

	private function mapToEntity(FetchRunEloquent $fetchRunEloquent): FetchRunEntity
	{
		return new FetchRunEntity(
			fetchRunId: new FetchRunId(id: $fetchRunEloquent->id),
			status: FetchStatusEnum::from((string) $fetchRunEloquent->status),
			source: $fetchRunEloquent->source,
			results: (array) ($fetchRunEloquent->results ?? []),
			message: $fetchRunEloquent->message,
			startedAt: $fetchRunEloquent->started_at,
			finishedAt: $fetchRunEloquent->finished_at,
		);
	}
}
