<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Carbon\CarbonImmutable;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun as PromptRunEloquent;
use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunEntityCollection;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Objects\PromptRunObject;

class PromptRunRepository implements PromptRunRepositoryContract
{
	public function create(PromptRunObject $promptRunObject): PromptRunEntity
	{
		$promptRunEloquent = PromptRunEloquent::query()->create([
			'card' => $promptRunObject->card,
			'project' => $promptRunObject->project,
			'branch' => $promptRunObject->branch,
			'directory' => $promptRunObject->directory,
			'ticket_key' => $promptRunObject->ticketKey,
			'session_uuid' => $promptRunObject->sessionUuid,
			'claims_session' => $promptRunObject->claimsSession,
			'prompt' => $promptRunObject->prompt,
			'status' => PromptStatusEnum::RUNNING->value,
			'started_at' => CarbonImmutable::now(),
		]);

		return $this->mapToEntity($promptRunEloquent);
	}

	public function getById(PromptRunId $promptRunId): ?PromptRunEntity
	{
		$promptRunEloquent = PromptRunEloquent::query()->find($promptRunId->id);

		return $promptRunEloquent === null ? null : $this->mapToEntity($promptRunEloquent);
	}

	public function getAllForCard(string $card): PromptRunEntityCollection
	{
		$promptRunEntities = PromptRunEloquent::query()
			->where('card', $card)
			->orderBy('id')
			->get()
			->map(fn (PromptRunEloquent $promptRunEloquent) => $this->mapToEntity($promptRunEloquent))
			->all();

		return PromptRunEntityCollection::fromArray($promptRunEntities);
	}

	public function getLatestForCard(string $card): ?PromptRunEntity
	{
		$promptRunEloquent = PromptRunEloquent::query()
			->where('card', $card)
			->latest('id')
			->first();

		return $promptRunEloquent === null ? null : $this->mapToEntity($promptRunEloquent);
	}

	public function getByReviewRounds(string $card): array
	{
		$byRound = [];

		$promptRunEloquents = PromptRunEloquent::query()
			->where('card', $card)
			->whereNotNull('review_round_id')
			->orderBy('id')
			->get();

		foreach ($promptRunEloquents as $promptRunEloquent) {
			$byRound[(int) $promptRunEloquent->review_round_id] = $this->mapToEntity($promptRunEloquent);
		}

		return $byRound;
	}

	public function markFinished(PromptRunId $promptRunId, PromptResultObject $promptResultObject): PromptRunEntity
	{
		$promptRunEloquent = PromptRunEloquent::query()->findOrFail($promptRunId->id);

		$promptRunEloquent->update([
			'status' => $promptResultObject->hasFailed
				? PromptStatusEnum::FAILED->value
				: PromptStatusEnum::DONE->value,
			'message' => $promptResultObject->message,
			'session_uuid' => $promptResultObject->sessionUuid ?? $promptRunEloquent->session_uuid,
			'output' => $promptResultObject->output,
			'cost' => $promptResultObject->cost,
			'finished_at' => CarbonImmutable::now(),
		]);

		return $this->mapToEntity($promptRunEloquent);
	}

	public function markFailed(PromptRunId $promptRunId, string $message): PromptRunEntity
	{
		$promptRunEloquent = PromptRunEloquent::query()->findOrFail($promptRunId->id);

		$promptRunEloquent->update([
			'status' => PromptStatusEnum::FAILED->value,
			'message' => $message,
			'finished_at' => CarbonImmutable::now(),
		]);

		return $this->mapToEntity($promptRunEloquent);
	}

	private function mapToEntity(PromptRunEloquent $promptRunEloquent): PromptRunEntity
	{
		return new PromptRunEntity(
			promptRunId: new PromptRunId(id: $promptRunEloquent->id),
			card: (string) $promptRunEloquent->card,
			reviewRoundId: $promptRunEloquent->review_round_id === null ? null : (int) $promptRunEloquent->review_round_id,
			project: $promptRunEloquent->project,
			branch: $promptRunEloquent->branch,
			directory: $promptRunEloquent->directory,
			ticketKey: $promptRunEloquent->ticket_key,
			sessionUuid: $promptRunEloquent->session_uuid,
			claimsSession: (bool) $promptRunEloquent->claims_session,
			prompt: (string) $promptRunEloquent->prompt,
			status: PromptStatusEnum::from((string) $promptRunEloquent->status),
			output: $promptRunEloquent->output,
			message: $promptRunEloquent->message,
			cost: $promptRunEloquent->cost,
			startedAt: $promptRunEloquent->started_at,
			finishedAt: $promptRunEloquent->finished_at,
		);
	}
}
