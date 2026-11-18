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
 * page stays free while it runs. A task that arrives while another of the same
 * card runs waits its turn: one working copy does one thing at a time.
 *
 * @method static bool execute(WorkingCopyTaskObject $workingCopyTaskObject)
 */
class StartWorkingCopyTaskAction extends Command
{
	public const KEY = 'worktree.task.';

	public const LOCK = 'worktree.task.lock.';

	public function __construct(
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {}

	public static function keyFor(string $card): string
	{
		return self::KEY . md5($card);
	}

	public static function lockFor(string $card): string
	{
		return self::LOCK . md5($card);
	}

	public function handle(DetachedCommand $detachedCommand): bool
	{
		$card = $this->workingCopyTaskObject->card;

		return Cache::lock(self::lockFor($card), 10)->block(5, function () use ($card, $detachedCommand): bool {
			$kept = (array) Cache::get(self::keyFor($card), []);

			if (GetWorkingCopyTaskAction::execute($card)?->isRunning === true) {
				Cache::forever(self::keyFor($card), [
					...$kept,
					'queued' => [...(array) ($kept['queued'] ?? []), $this->workingCopyTaskObject->toArgument()],
				]);

				return true;
			}

			Cache::forever(self::keyFor($card), [
				'task' => $this->workingCopyTaskObject->task->value,
				'current' => $this->workingCopyTaskObject->toArgument(),
				'is_running' => true,
				'started_at' => CarbonImmutable::now()->toIso8601String(),
				'failure' => null,
				'queued' => [],
				'endings' => (array) ($kept['endings'] ?? []),
			]);

			// the process reads the task from the card, so a long prompt or commit message never meets the length limit of a command line
			$detachedCommand->artisan('devdock:working-copy-task --card=' . escapeshellarg($card));

			return true;
		});
	}
}
