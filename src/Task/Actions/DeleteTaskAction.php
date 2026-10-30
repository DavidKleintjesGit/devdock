<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;

/**
 * Takes a task off the board: its ties and its agents go with it. What the
 * ties pointed at — the ticket, the pull request, the branch — stays where it
 * was, so the board works them out from names again.
 *
 * @method static void execute(string $key)
 */
class DeleteTaskAction extends Command
{
	public function __construct(
		public readonly string $key,
	) {}

	public function handle(TaskRepositoryContract $taskRepository): void
	{
		$taskEntity = $taskRepository->getByKey($this->key);

		if ($taskEntity !== null) {
			$taskRepository->delete($taskEntity->taskId);
		}
	}
}
