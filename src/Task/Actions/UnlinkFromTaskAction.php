<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Objects\TaskEntity;

/**
 * @method static ?TaskEntity execute(string $key, TaskLinkKindEnum $kind, string $value)
 */
class UnlinkFromTaskAction extends Command
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
			: $taskRepository->unlink($taskEntity->taskId, $this->kind, $this->value);
	}
}
