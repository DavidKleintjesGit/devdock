<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Objects\TodoId;
use Src\Packages\Cqrs\Command;

/**
 * @method static void execute(TodoId $todoId)
 */
class DeleteTodoAction extends Command
{
	public function __construct(
		public readonly TodoId $todoId,
	) {
	}

	public function handle(TodoRepositoryContract $todoRepository): void
	{
		$todoRepository->delete($this->todoId);
	}
}
