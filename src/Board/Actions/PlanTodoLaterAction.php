<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Objects\TodoEntity;
use Src\Board\Objects\TodoId;
use Src\Packages\Cqrs\Command;

/**
 * Moves a to-do to the backlog, or back into the sprint.
 *
 * @method static TodoEntity execute(TodoId $todoId, bool $isLater)
 */
class PlanTodoLaterAction extends Command
{
	public function __construct(
		public readonly TodoId $todoId,
		public readonly bool $isLater,
	) {
	}

	public function handle(TodoRepositoryContract $todoRepository): TodoEntity
	{
		return $todoRepository->planLater($this->todoId, $this->isLater);
	}
}
