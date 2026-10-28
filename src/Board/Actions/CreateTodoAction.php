<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Objects\TodoEntity;
use Src\Board\Objects\TodoObject;
use Src\Packages\Cqrs\Command;

/**
 * @method static TodoEntity execute(TodoObject $todoObject)
 */
class CreateTodoAction extends Command
{
	public function __construct(
		public readonly TodoObject $todoObject,
	) {
	}

	public function handle(TodoRepositoryContract $todoRepository): TodoEntity
	{
		return $todoRepository->create($this->todoObject);
	}
}
