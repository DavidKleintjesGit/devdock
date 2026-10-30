<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Objects\TaskEntity;

/**
 * Pins a branch, a pull request, a ticket or a project to a task, so the board
 * stops guessing and keeps them together.
 *
 * @method static TaskEntity execute(string $key, TaskLinkKindEnum $kind, string $value)
 */
class LinkToTaskAction extends Command
{
	public function __construct(
		public readonly string $key,
		public readonly TaskLinkKindEnum $kind,
		public readonly string $value,
	) {}

	public function handle(TaskRepositoryContract $taskRepository): TaskEntity
	{
		$taskEntity = $taskRepository->firstOrCreate($this->key);

		return $taskRepository->link($taskEntity->taskId, $this->kind, $this->value);
	}
}
