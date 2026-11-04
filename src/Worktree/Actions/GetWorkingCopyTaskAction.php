<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Objects\WorkingCopyTaskStateObject;

/**
 * The last background task on the working copy of a card, or null when it
 * never had one.
 *
 * A task that says it runs but started longer ago than any task may take
 * fell over without writing its end; it counts as stopped.
 *
 * @method static ?WorkingCopyTaskStateObject execute(string $card)
 */
class GetWorkingCopyTaskAction extends Command
{
	private const STALE_MINUTES = 30;

	public function __construct(
		public readonly string $card,
	) {}

	public function handle(): ?WorkingCopyTaskStateObject
	{
		$kept = Cache::get(StartWorkingCopyTaskAction::keyFor($this->card));

		if (! is_array($kept)) {
			return null;
		}

		$workingCopyTaskStateObject = WorkingCopyTaskStateObject::from($kept);

		if ($workingCopyTaskStateObject->isRunning && $workingCopyTaskStateObject->startedAt->addMinutes(self::STALE_MINUTES)->isPast()) {
			return WorkingCopyTaskStateObject::from([...$kept, 'is_running' => false, 'failure' => __('De taak stopte zonder af te ronden.')]);
		}

		return $workingCopyTaskStateObject;
	}
}
