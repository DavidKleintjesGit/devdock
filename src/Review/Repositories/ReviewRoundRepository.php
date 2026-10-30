<?php

declare(strict_types=1);

namespace Src\Review\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewResult as ReviewResultEloquent;
use Src\Review\Models\ReviewRound as ReviewRoundEloquent;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundEntityCollection;
use Src\Review\Objects\ReviewRoundId;

class ReviewRoundRepository implements ReviewRoundRepositoryContract
{
	public function getForCard(string $card): ReviewRoundEntityCollection
	{
		$reviewRoundEntities = ReviewRoundEloquent::query()
			->with(['results.lens'])
			->where('card', $card)
			->orderBy('number')
			->get()
			->map(fn (ReviewRoundEloquent $reviewRoundEloquent) => $this->mapToEntity($reviewRoundEloquent))
			->all();

		return ReviewRoundEntityCollection::fromArray($reviewRoundEntities);
	}

	public function getLatestPerCard(): array
	{
		$latest = [];

		// het bord vraagt dit voor elke kaart; één vraag in plaats van honderd
		$reviewRoundEloquents = ReviewRoundEloquent::query()
			->with(['results.lens'])
			->orderBy('number')
			->get();

		foreach ($reviewRoundEloquents as $reviewRoundEloquent) {
			$latest[(string) $reviewRoundEloquent->card] = $this->mapToEntity($reviewRoundEloquent);
		}

		return $latest;
	}

	public function getById(ReviewRoundId $reviewRoundId): ?ReviewRoundEntity
	{
		$reviewRoundEloquent = ReviewRoundEloquent::query()
			->with(['results.lens'])
			->find($reviewRoundId->id);

		return $reviewRoundEloquent === null ? null : $this->mapToEntity($reviewRoundEloquent);
	}

	public function getRunning(): ReviewRoundEntityCollection
	{
		$reviewRoundEntities = ReviewRoundEloquent::query()
			->with(['results.lens'])
			->where('status', RoundStatusEnum::RUNNING->value)
			->orderByDesc('id')
			->get()
			->map(fn (ReviewRoundEloquent $reviewRoundEloquent) => $this->mapToEntity($reviewRoundEloquent))
			->all();

		return ReviewRoundEntityCollection::fromArray($reviewRoundEntities);
	}

	public function open(string $card, ?string $commitSha, ?string $directory, array $lenses): ReviewRoundEntity
	{
		$number = ((int) ReviewRoundEloquent::query()->where('card', $card)->max('number')) + 1;

		$reviewRoundEloquent = ReviewRoundEloquent::query()->create([
			'card' => $card,
			'number' => $number,
			'commit_sha' => $commitSha,
			'directory' => $directory,
			'status' => RoundStatusEnum::RUNNING->value,
			'started_at' => CarbonImmutable::now(),
		]);

		foreach ($lenses as $lens) {
			ReviewResultEloquent::query()->create([
				'review_round_id' => $reviewRoundEloquent->id,
				'lens_id' => $lens['lens_id']->id,
				'verdict' => $lens['verdict']->value,
				'touched_files' => $lens['touched_files'],
				'finished_at' => $lens['verdict'] === VerdictEnum::SKIPPED ? CarbonImmutable::now() : null,
			]);
		}

		return $this->byId(new ReviewRoundId(id: $reviewRoundEloquent->id));
	}

	public function markResultRunning(ReviewRoundId $reviewRoundId, LensId $lensId, ?string $sessionUuid): void
	{
		ReviewResultEloquent::query()
			->where('review_round_id', $reviewRoundId->id)
			->where('lens_id', $lensId->id)
			->update([
				'verdict' => VerdictEnum::RUNNING->value,
				'session_uuid' => $sessionUuid,
				'activity' => null,
				'tool_calls' => 0,
				'cost' => null,
				'started_at' => CarbonImmutable::now(),
				'last_seen_at' => CarbonImmutable::now(),
				'finished_at' => null,
			]);
	}

	public function markResultProgress(
		ReviewRoundId $reviewRoundId,
		LensId $lensId,
		?string $activity,
		int $toolCalls,
		?float $cost,
		?string $sessionUuid,
	): void {
		ReviewResultEloquent::query()
			->where('review_round_id', $reviewRoundId->id)
			->where('lens_id', $lensId->id)
			->update(array_filter([
				'activity' => $activity,
				'cost' => $cost,
				'session_uuid' => $sessionUuid,
			], fn (mixed $value) => $value !== null) + [
				'tool_calls' => $toolCalls,
				'last_seen_at' => CarbonImmutable::now(),
			]);
	}

	public function writeResult(
		ReviewRoundId $reviewRoundId,
		LensId $lensId,
		VerdictEnum $verdict,
		int $findings,
		?string $report,
	): ReviewRoundEntity {
		ReviewResultEloquent::query()
			->where('review_round_id', $reviewRoundId->id)
			->where('lens_id', $lensId->id)
			->update([
				'verdict' => $verdict->value,
				'findings' => $findings,
				'report' => $report,
				'finished_at' => CarbonImmutable::now(),
			]);

		return $this->settle($reviewRoundId);
	}

	public function settle(ReviewRoundId $reviewRoundId): ReviewRoundEntity
	{
		$reviewRoundEntity = $this->byId($reviewRoundId);

		$working = collect($reviewRoundEntity->results)->contains(
			fn (ReviewResultEntity $resultEntity) => ! $resultEntity->verdict->isSettled(),
		);

		if ($working) {
			return $reviewRoundEntity;
		}

		ReviewRoundEloquent::query()->whereKey($reviewRoundId->id)->update([
			'status' => $reviewRoundEntity->isClean()
				? RoundStatusEnum::PASSED->value
				: RoundStatusEnum::FAILED->value,
			'finished_at' => CarbonImmutable::now(),
		]);

		return $this->byId($reviewRoundId);
	}

	public function reopen(ReviewRoundId $reviewRoundId): ReviewRoundEntity
	{
		ReviewRoundEloquent::query()->whereKey($reviewRoundId->id)->update([
			'status' => RoundStatusEnum::RUNNING->value,
			'finished_at' => null,
		]);

		return $this->byId($reviewRoundId);
	}

	public function stop(ReviewRoundId $reviewRoundId): ReviewRoundEntity
	{
		ReviewRoundEloquent::query()->whereKey($reviewRoundId->id)->update([
			'status' => RoundStatusEnum::STOPPED->value,
			'finished_at' => CarbonImmutable::now(),
		]);

		ReviewResultEloquent::query()
			->where('review_round_id', $reviewRoundId->id)
			->whereIn('verdict', [VerdictEnum::WAITING->value, VerdictEnum::RUNNING->value])
			->update(['verdict' => VerdictEnum::SKIPPED->value, 'finished_at' => CarbonImmutable::now()]);

		return $this->byId($reviewRoundId);
	}

	public function deleteForCard(string $card): void
	{
		ReviewRoundEloquent::query()->where('card', $card)->delete();
	}

	private function byId(ReviewRoundId $reviewRoundId): ReviewRoundEntity
	{
		return $this->mapToEntity(
			ReviewRoundEloquent::query()->with(['results.lens'])->findOrFail($reviewRoundId->id),
		);
	}

	private function mapToEntity(ReviewRoundEloquent $reviewRoundEloquent): ReviewRoundEntity
	{
		return new ReviewRoundEntity(
			reviewRoundId: new ReviewRoundId(id: $reviewRoundEloquent->id),
			card: (string) $reviewRoundEloquent->card,
			number: (int) $reviewRoundEloquent->number,
			commitSha: $reviewRoundEloquent->commit_sha,
			directory: $reviewRoundEloquent->directory,
			status: RoundStatusEnum::from((string) $reviewRoundEloquent->status),
			results: $this->resultsOf($reviewRoundEloquent->results),
			startedAt: $reviewRoundEloquent->started_at,
			finishedAt: $reviewRoundEloquent->finished_at,
		);
	}

	/**
	 * @param  Collection<int, ReviewResultEloquent>  $reviewResultEloquents
	 * @return array<int, ReviewResultEntity>
	 */
	private function resultsOf(Collection $reviewResultEloquents): array
	{
		return $reviewResultEloquents
			->sortBy(fn (ReviewResultEloquent $reviewResultEloquent) => $reviewResultEloquent->lens?->position ?? 0)
			->map(fn (ReviewResultEloquent $reviewResultEloquent) => new ReviewResultEntity(
				lensId: new LensId(id: $reviewResultEloquent->lens_id),
				lensName: (string) ($reviewResultEloquent->lens?->name ?? __('verwijderde lens')),
				verdict: VerdictEnum::from((string) $reviewResultEloquent->verdict),
				findings: (int) $reviewResultEloquent->findings,
				report: $reviewResultEloquent->report,
				touchedFiles: (int) $reviewResultEloquent->touched_files,
				sessionUuid: $reviewResultEloquent->session_uuid,
				activity: $reviewResultEloquent->activity,
				toolCalls: (int) $reviewResultEloquent->tool_calls,
				cost: $reviewResultEloquent->cost === null ? null : (float) $reviewResultEloquent->cost,
				startedAt: $reviewResultEloquent->started_at,
				lastSeenAt: $reviewResultEloquent->last_seen_at,
				finishedAt: $reviewResultEloquent->finished_at,
			))
			->values()
			->all();
	}
}
