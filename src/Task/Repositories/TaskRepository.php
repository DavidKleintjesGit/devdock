<?php

declare(strict_types=1);

namespace Src\Task\Repositories;

use Illuminate\Support\Collection;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Models\Task as TaskEloquent;
use Src\Task\Models\TaskAgent as TaskAgentEloquent;
use Src\Task\Models\TaskLink as TaskLinkEloquent;
use Src\Task\Objects\TaskAgentEntity;
use Src\Task\Objects\TaskAgentEntityCollection;
use Src\Task\Objects\TaskEntity;
use Src\Task\Objects\TaskEntityCollection;
use Src\Task\Objects\TaskId;
use Src\Task\Objects\TaskLinkEntity;
use Src\Task\Objects\TaskLinkEntityCollection;

class TaskRepository implements TaskRepositoryContract
{
	public function getAll(): TaskEntityCollection
	{
		$taskEntities = TaskEloquent::query()
			->with(['links', 'agents'])
			->orderBy('key')
			->get()
			->map(fn (TaskEloquent $taskEloquent) => $this->mapToEntity($taskEloquent))
			->all();

		return TaskEntityCollection::fromArray($taskEntities);
	}

	public function getByKey(string $key): ?TaskEntity
	{
		$taskEloquent = TaskEloquent::query()->with(['links', 'agents'])->where('key', $key)->first();

		return $taskEloquent === null ? null : $this->mapToEntity($taskEloquent);
	}

	public function firstOrCreate(string $key): TaskEntity
	{
		$taskEloquent = TaskEloquent::query()->firstOrCreate(['key' => $key]);

		return $this->byId(new TaskId(id: $taskEloquent->id));
	}

	public function link(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity
	{
		$hasActive = TaskLinkEloquent::query()
			->where('task_id', $taskId->id)
			->where('kind', $kind->value)
			->where('is_active', true)
			->exists();

		// a value belongs to one task, so pinning it here takes it off the other
		TaskLinkEloquent::query()->updateOrCreate(
			['kind' => $kind->value, 'value' => $value],
			['task_id' => $taskId->id, 'is_active' => ! $hasActive],
		);

		return $this->byId($taskId);
	}

	public function activate(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity
	{
		$ofKind = TaskLinkEloquent::query()
			->where('task_id', $taskId->id)
			->where('kind', $kind->value);

		(clone $ofKind)->update(['is_active' => false]);
		(clone $ofKind)->where('value', $value)->update(['is_active' => true]);

		return $this->byId($taskId);
	}

	public function unlink(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity
	{
		TaskLinkEloquent::query()
			->where('task_id', $taskId->id)
			->where('kind', $kind->value)
			->where('value', $value)
			->delete();

		$this->promoteLink($taskId, $kind);

		return $this->byId($taskId);
	}

	public function delete(TaskId $taskId): void
	{
		TaskEloquent::query()->whereKey($taskId->id)->delete();
	}

	public function openAgent(TaskId $taskId, string $sessionUuid, string $name, ?string $directory): TaskEntity
	{
		TaskAgentEloquent::query()->where('task_id', $taskId->id)->update(['is_active' => false]);

		TaskAgentEloquent::query()->updateOrCreate(
			['session_uuid' => $sessionUuid],
			['task_id' => $taskId->id, 'name' => $name, 'directory' => $directory, 'is_active' => true],
		);

		return $this->byId($taskId);
	}

	public function activateAgent(TaskId $taskId, string $sessionUuid): TaskEntity
	{
		$ofTask = TaskAgentEloquent::query()->where('task_id', $taskId->id);

		(clone $ofTask)->update(['is_active' => false]);
		(clone $ofTask)->where('session_uuid', $sessionUuid)->update(['is_active' => true]);

		return $this->byId($taskId);
	}

	public function markAgentStarted(string $sessionUuid): void
	{
		TaskAgentEloquent::query()
			->where('session_uuid', $sessionUuid)
			->whereNull('started_at')
			->update(['started_at' => now()]);
	}

	/**
	 * Keeps a kind from standing without an active tie after one was removed:
	 * the oldest of what is left takes over.
	 */
	private function promoteLink(TaskId $taskId, TaskLinkKindEnum $kind): void
	{
		$ofKind = TaskLinkEloquent::query()
			->where('task_id', $taskId->id)
			->where('kind', $kind->value);

		if ((clone $ofKind)->where('is_active', true)->exists()) {
			return;
		}

		$oldest = (clone $ofKind)->orderBy('id')->first();

		$oldest?->update(['is_active' => true]);
	}

	private function byId(TaskId $taskId): TaskEntity
	{
		return $this->mapToEntity(TaskEloquent::query()->with(['links', 'agents'])->findOrFail($taskId->id));
	}

	private function mapToEntity(TaskEloquent $taskEloquent): TaskEntity
	{
		return new TaskEntity(
			taskId: new TaskId(id: $taskEloquent->id),
			key: (string) $taskEloquent->key,
			links: $this->linksOf($taskEloquent->links),
			agents: $this->agentsOf($taskEloquent->agents),
			createdAt: $taskEloquent->created_at?->toImmutable(),
		);
	}

	/**
	 * @param  Collection<int, TaskLinkEloquent>  $taskLinkEloquents
	 */
	private function linksOf(Collection $taskLinkEloquents): TaskLinkEntityCollection
	{
		$taskLinkEntities = $taskLinkEloquents
			->map(fn (TaskLinkEloquent $taskLinkEloquent) => new TaskLinkEntity(
				kind: TaskLinkKindEnum::from((string) $taskLinkEloquent->kind),
				value: (string) $taskLinkEloquent->value,
				isActive: (bool) $taskLinkEloquent->is_active,
			))
			->all();

		return TaskLinkEntityCollection::fromArray($taskLinkEntities);
	}

	/**
	 * @param  Collection<int, TaskAgentEloquent>  $taskAgentEloquents
	 */
	private function agentsOf(Collection $taskAgentEloquents): TaskAgentEntityCollection
	{
		$taskAgentEntities = $taskAgentEloquents
			->sortByDesc('id')
			->map(fn (TaskAgentEloquent $taskAgentEloquent) => new TaskAgentEntity(
				sessionUuid: (string) $taskAgentEloquent->session_uuid,
				name: (string) ($taskAgentEloquent->name ?: TaskAgentEntity::nameFor((string) $taskAgentEloquent->session_uuid)),
				directory: $taskAgentEloquent->directory,
				isActive: (bool) $taskAgentEloquent->is_active,
				startedAt: $taskAgentEloquent->started_at,
				createdAt: $taskAgentEloquent->created_at?->toImmutable(),
			))
			->values()
			->all();

		return TaskAgentEntityCollection::fromArray($taskAgentEntities);
	}
}
