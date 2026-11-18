<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\GetWorkingCopyTaskAction;
use Src\Worktree\Actions\RunWorkingCopyTaskAction;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Runs a task on the working copy of a card in the background, such as
 * making it, so the page that asked for it stays free. The tasks that waited
 * behind it run next, one after the other, in the same process.
 */
class WorkingCopyTaskCommand extends Command
{
	protected $signature = 'devdock:working-copy-task {--card= : The card whose task runs now}';

	protected $description = 'Run a task on the working copy of a card in the background';

	public function handle(): int
	{
		$current = GetWorkingCopyTaskAction::execute((string) $this->option('card'))?->current;

		if ($current === null) {
			$this->components->error('This card has no task to run.');

			return self::FAILURE;
		}

		$workingCopyTaskStateObject = RunWorkingCopyTaskAction::execute(WorkingCopyTaskObject::fromArgument($current));

		while ($workingCopyTaskStateObject->next !== null) {
			$workingCopyTaskStateObject = RunWorkingCopyTaskAction::execute(WorkingCopyTaskObject::fromArgument($workingCopyTaskStateObject->next));
		}

		if ($workingCopyTaskStateObject->failure !== null) {
			$this->components->error($workingCopyTaskStateObject->failure);

			return self::FAILURE;
		}

		$this->components->info('Done.');

		return self::SUCCESS;
	}
}
