<?php

declare(strict_types=1);

namespace Src\Task\Actions;

use Illuminate\Support\Str;
use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Objects\TaskAgentEntity;

/**
 * Hands a task its agent. The key is minted here and written down before any
 * prompt leaves, so the session is ours from the first round instead of
 * something we recognise afterwards.
 *
 * Opening a second one replaces the first: a task is worked in one session.
 *
 * @method static TaskAgentEntity execute(string $key, ?string $directory)
 */
class OpenAgentAction extends Command
{
	public function __construct(
		public readonly string $key,
		public readonly ?string $directory,
	) {}

	public function handle(TaskRepositoryContract $taskRepository): TaskAgentEntity
	{
		$taskEntity = $taskRepository->firstOrCreate($this->key);

		$taskEntity = $taskRepository->openAgent(
			$taskEntity->taskId,
			Str::uuid()->toString(),
			$this->nameBesides($taskEntity->agents),
			$this->directory,
		);

		return $taskEntity->activeAgent();
	}

	/**
	 * A name the task has not used yet, so two agents on one task are never
	 * mixed up. With every name taken it starts over.
	 */
	private function nameBesides(iterable $agents): string
	{
		$taken = [];

		foreach ($agents as $taskAgentEntity) {
			$taken[] = $taskAgentEntity->name;
		}

		$free = array_values(array_diff(TaskAgentEntity::NAMES, $taken));

		return $free === []
			? TaskAgentEntity::NAMES[array_rand(TaskAgentEntity::NAMES)]
			: $free[array_rand($free)];
	}
}
