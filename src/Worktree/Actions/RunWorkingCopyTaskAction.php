<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;
use Src\Worktree\Objects\WorkingCopyTaskStateObject;
use Throwable;

/**
 * Carries out a task on the working copy of a card, in the process that was
 * started for it, and writes down how it ended.
 *
 * @method static WorkingCopyTaskStateObject execute(WorkingCopyTaskObject $workingCopyTaskObject)
 */
class RunWorkingCopyTaskAction extends Command
{
	public function __construct(
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {}

	public function handle(): WorkingCopyTaskStateObject
	{
		$key = StartWorkingCopyTaskAction::keyFor($this->workingCopyTaskObject->card);
		$startedAt = (array) Cache::get($key, []);

		try {
			$failure = match ($this->workingCopyTaskObject->task) {
				WorkingCopyTaskEnum::LINK => LinkWorkingCopyAction::execute(
					$this->workingCopyTaskObject->card,
					$this->workingCopyTaskObject->project,
					$this->workingCopyTaskObject->branch,
					$this->workingCopyTaskObject->fill,
					$this->workingCopyTaskObject->carriesChanges,
				),
			};
		} catch (Throwable $exception) {
			report($exception);
			$failure = $exception->getMessage();
		}

		$data = [
			'task' => $this->workingCopyTaskObject->task->value,
			'is_running' => false,
			'started_at' => $startedAt['started_at'] ?? CarbonImmutable::now()->toIso8601String(),
			'failure' => $failure,
		];

		Cache::forever($key, $data);

		return WorkingCopyTaskStateObject::from($data);
	}
}
