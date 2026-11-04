<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\RunWorkingCopyTaskAction;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Runs a task on the working copy of a card in the background, such as
 * making it, so the page that asked for it stays free.
 */
class WorkingCopyTaskCommand extends Command
{
	protected $signature = 'devdock:working-copy-task {--task= : The task, as StartWorkingCopyTaskAction hands it over}';

	protected $description = 'Run a task on the working copy of a card in the background';

	public function handle(): int
	{
		$workingCopyTaskStateObject = RunWorkingCopyTaskAction::execute(WorkingCopyTaskObject::fromArgument((string) $this->option('task')));

		if ($workingCopyTaskStateObject->failure !== null) {
			$this->components->error($workingCopyTaskStateObject->failure);

			return self::FAILURE;
		}

		$this->components->info('Done.');

		return self::SUCCESS;
	}
}
