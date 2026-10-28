<?php

declare(strict_types=1);

namespace Src\Board\Repositories;

use Carbon\CarbonImmutable;
use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Models\Todo as TodoEloquent;
use Src\Board\Objects\TodoEntity;
use Src\Board\Objects\TodoEntityCollection;
use Src\Board\Objects\TodoId;
use Src\Board\Objects\TodoObject;

class TodoRepository implements TodoRepositoryContract
{
	public function create(TodoObject $todoObject): TodoEntity
	{
		$todoEloquent = TodoEloquent::query()->create([
			'title' => $todoObject->title,
			'note' => $todoObject->note,
			'project' => $todoObject->project,
		]);

		return $this->mapToEntity($todoEloquent);
	}

	public function getAll(): TodoEntityCollection
	{
		$todoEntities = TodoEloquent::query()
			->orderByDesc('id')
			->get()
			->map(fn (TodoEloquent $todoEloquent) => $this->mapToEntity($todoEloquent))
			->all();

		return TodoEntityCollection::fromArray($todoEntities);
	}

	public function getById(TodoId $todoId): ?TodoEntity
	{
		$todoEloquent = TodoEloquent::query()->find($todoId->id);

		return $todoEloquent === null ? null : $this->mapToEntity($todoEloquent);
	}

	public function markDone(TodoId $todoId, bool $isDone): TodoEntity
	{
		$todoEloquent = TodoEloquent::query()->findOrFail($todoId->id);

		$todoEloquent->update(['done_at' => $isDone ? CarbonImmutable::now() : null]);

		return $this->mapToEntity($todoEloquent);
	}

	public function delete(TodoId $todoId): void
	{
		TodoEloquent::query()->whereKey($todoId->id)->delete();
	}

	private function mapToEntity(TodoEloquent $todoEloquent): TodoEntity
	{
		return new TodoEntity(
			todoId: new TodoId(id: $todoEloquent->id),
			title: (string) $todoEloquent->title,
			note: $todoEloquent->note,
			project: $todoEloquent->project,
			doneAt: $todoEloquent->done_at,
			changedAt: $todoEloquent->updated_at?->toImmutable(),
		);
	}
}
