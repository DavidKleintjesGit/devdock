<?php

declare(strict_types=1);

namespace Src\Session\Repositories;

use Carbon\CarbonImmutable;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Enums\SessionGroupEnum;
use Src\Session\Enums\SessionStatusEnum;
use Src\Session\Models\ClaudeSession as ClaudeSessionEloquent;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionId;
use Src\Session\Objects\SessionObject;
use Src\Session\Objects\SessionObjectCollection;
use Src\Session\Objects\SessionThresholdObject;

class SessionRepository implements SessionRepositoryContract
{
	public function create(SessionObject $sessionObject, SessionThresholdObject $thresholds): SessionEntity
	{
		$claudeSessionEloquent = ClaudeSessionEloquent::query()->updateOrCreate(
			['uuid' => $sessionObject->uuid],
			[
				'folder' => $sessionObject->folder,
				'project' => $sessionObject->project,
				'title' => $sessionObject->title,
				'branch' => $sessionObject->branch,
				'prompt_count' => $sessionObject->promptCount,
				'bytes' => $sessionObject->bytes,
				'last_active_at' => $sessionObject->lastActiveAt,
				'fingerprint' => $sessionObject->fingerprint,
			],
		);

		return $this->mapToEntity($claudeSessionEloquent, $thresholds);
	}

	public function getAll(SessionThresholdObject $thresholds): SessionEntityCollection
	{
		$sessionEntities = ClaudeSessionEloquent::query()
			->orderByDesc('last_active_at')
			->get()
			->map(fn (ClaudeSessionEloquent $claudeSessionEloquent) => $this->mapToEntity($claudeSessionEloquent, $thresholds))
			->all();

		return SessionEntityCollection::fromArray($sessionEntities);
	}

	public function getById(SessionId $sessionId, SessionThresholdObject $thresholds): ?SessionEntity
	{
		$claudeSessionEloquent = ClaudeSessionEloquent::query()
			->when($sessionId->id !== null, fn ($query) => $query->whereKey($sessionId->id))
			->when($sessionId->id === null, fn ($query) => $query->where('uuid', $sessionId->uuid))
			->first();

		return $claudeSessionEloquent === null ? null : $this->mapToEntity($claudeSessionEloquent, $thresholds);
	}

	public function replaceAll(
		SessionObjectCollection $sessionObjects,
		SessionThresholdObject $thresholds,
	): SessionEntityCollection {
		$uuids = [];

		foreach ($sessionObjects as $sessionObject) {
			$this->create($sessionObject, $thresholds);
			$uuids[] = $sessionObject->uuid;
		}

		if ($uuids !== []) {
			ClaudeSessionEloquent::query()->whereNotIn('uuid', $uuids)->delete();
		}

		return $this->getAll($thresholds);
	}

	public function updateTitle(SessionId $sessionId, string $title, string $fingerprint): void
	{
		ClaudeSessionEloquent::query()
			->whereKey($sessionId->id)
			->update([
				'title' => $title,
				'fingerprint' => $fingerprint,
			]);
	}

	public function delete(SessionId $sessionId): void
	{
		ClaudeSessionEloquent::query()->whereKey($sessionId->id)->delete();
	}

	private function mapToEntity(
		ClaudeSessionEloquent $claudeSessionEloquent,
		SessionThresholdObject $thresholds,
	): SessionEntity {
		$promptCount = (int) $claudeSessionEloquent->prompt_count;
		$bytes = (int) $claudeSessionEloquent->bytes;
		$lastActiveAt = $claudeSessionEloquent->last_active_at;

		return new SessionEntity(
			sessionId: new SessionId(id: $claudeSessionEloquent->id, uuid: $claudeSessionEloquent->uuid),
			uuid: (string) $claudeSessionEloquent->uuid,
			folder: (string) $claudeSessionEloquent->folder,
			project: $claudeSessionEloquent->project,
			title: $claudeSessionEloquent->title,
			branch: $claudeSessionEloquent->branch,
			promptCount: $promptCount,
			bytes: $bytes,
			lastActiveAt: $lastActiveAt,
			fingerprint: (string) $claudeSessionEloquent->fingerprint,
			status: $this->statusOf($promptCount, $lastActiveAt, $thresholds),
			group: $this->groupOf($lastActiveAt),
			isLarge: $bytes >= $thresholds->largeFromBytes(),
		);
	}

	private function statusOf(
		int $promptCount,
		?CarbonImmutable $lastActiveAt,
		SessionThresholdObject $thresholds,
	): SessionStatusEnum {
		if ($promptCount <= $thresholds->abandonedUpToPrompts) {
			return SessionStatusEnum::ABANDONED;
		}

		return $lastActiveAt?->isToday() ? SessionStatusEnum::ACTIVE : SessionStatusEnum::QUIET;
	}

	private function groupOf(?CarbonImmutable $lastActiveAt): SessionGroupEnum
	{
		if ($lastActiveAt?->isToday()) {
			return SessionGroupEnum::TODAY;
		}

		return $lastActiveAt?->isAfter(CarbonImmutable::now()->subWeek())
			? SessionGroupEnum::THIS_WEEK
			: SessionGroupEnum::OLDER;
	}
}
