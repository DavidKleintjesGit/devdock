<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Objects\TaskEntityCollection;

/**
 * @method static TaskEntityCollection execute()
 */
class GetTasksAction extends Command
{
	public function handle(TaskRepositoryContract $taskRepository): TaskEntityCollection
	{
		return $taskRepository->getAll();
	}
}
