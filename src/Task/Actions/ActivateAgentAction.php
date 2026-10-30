<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Objects\TaskEntity;

/**
 * Puts an earlier agent of this task back in charge. What was active stays on
 * the task, so nothing is lost by switching back.
 *
 * @method static ?TaskEntity execute(string $key, string $sessionUuid)
 */
class ActivateAgentAction extends Command
{
	public function __construct(
		public readonly string $key,
		public readonly string $sessionUuid,
	) {}

	public function handle(TaskRepositoryContract $taskRepository): ?TaskEntity
	{
		$taskEntity = $taskRepository->getByKey($this->key);

		return $taskEntity === null
			? null
			: $taskRepository->activateAgent($taskEntity->taskId, $this->sessionUuid);
	}
}
