<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Objects\TodoEntity;
use Src\Board\Objects\TodoId;
use Src\Packages\Cqrs\Command;

/**
 * Marks a to-do done, or puts it back on the list.
 *
 * @method static TodoEntity execute(TodoId $todoId, bool $isDone)
 */
class MarkTodoDoneAction extends Command
{
	public function __construct(
		public readonly TodoId $todoId,
		public readonly bool $isDone,
	) {
	}

	public function handle(TodoRepositoryContract $todoRepository): TodoEntity
	{
		return $todoRepository->markDone($this->todoId, $this->isDone);
	}
}
