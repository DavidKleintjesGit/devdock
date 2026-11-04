<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Packages\Process\DetachedCommand;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Puts a task on the working copy of a card in a process of its own, so the
 * page stays free while it runs. Returns false when a task of this card is
 * still running.
 *
 * @method static bool execute(WorkingCopyTaskObject $workingCopyTaskObject)
 */
class StartWorkingCopyTaskAction extends Command
{
	public const KEY = 'worktree.task.';

	public function __construct(
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {}

	public static function keyFor(string $card): string
	{
		return self::KEY . md5($card);
	}

	public function handle(DetachedCommand $detachedCommand): bool
	{
		if (GetWorkingCopyTaskAction::execute($this->workingCopyTaskObject->card)?->isRunning === true) {
			return false;
		}

		Cache::forever(self::keyFor($this->workingCopyTaskObject->card), [
			'task' => $this->workingCopyTaskObject->task->value,
			'is_running' => true,
			'started_at' => CarbonImmutable::now()->toIso8601String(),
			'failure' => null,
		]);

		$detachedCommand->artisan('devdock:working-copy-task --task=' . $this->workingCopyTaskObject->toArgument());

		return true;
	}
}
