<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Objects\TaskEntity;

/**
 * Settles which of the pinned ties of a kind the task works with. The rest
 * stay on the task.
 *
 * @method static ?TaskEntity execute(string $key, TaskLinkKindEnum $kind, string $value)
 */
class ActivateLinkAction extends Command
{
	public function __construct(
		public readonly string $key,
		public readonly TaskLinkKindEnum $kind,
		public readonly string $value,
	) {}

	public function handle(TaskRepositoryContract $taskRepository): ?TaskEntity
	{
		$taskEntity = $taskRepository->getByKey($this->key);

		return $taskEntity === null
			? null
			: $taskRepository->activate($taskEntity->taskId, $this->kind, $this->value);
	}
}
