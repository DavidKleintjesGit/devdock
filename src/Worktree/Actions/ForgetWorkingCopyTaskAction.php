<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;

/**
 * Forgets the last background task on the working copy of a card, once you
 * read how it ended. A task that still runs is left alone.
 *
 * @method static void execute(string $card)
 */
class ForgetWorkingCopyTaskAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(): void
	{
		if (GetWorkingCopyTaskAction::execute($this->card)?->isRunning !== true) {
			Cache::forget(StartWorkingCopyTaskAction::keyFor($this->card));
		}
	}
}
