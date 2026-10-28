<?php

declare(strict_types=1);

namespace Src\Board\Contracts;

use Src\Board\Objects\TodoEntity;
use Src\Board\Objects\TodoEntityCollection;
use Src\Board\Objects\TodoId;
use Src\Board\Objects\TodoObject;

interface TodoRepositoryContract
{
	public function create(TodoObject $todoObject): TodoEntity;

	public function getAll(): TodoEntityCollection;

	public function getById(TodoId $todoId): ?TodoEntity;

	/**
	 * Marks the to-do done or puts it back on the list.
	 */
	public function markDone(TodoId $todoId, bool $isDone): TodoEntity;

	public function delete(TodoId $todoId): void;
}
