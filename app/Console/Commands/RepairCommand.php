<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\RepairSetupAction;
use Src\Worktree\Enums\SetupCheckEnum;

/**
 * Repairs a working copy in the background, one point or the whole list.
 */
class RepairCommand extends Command
{
	protected $signature = 'devdock:repair {--directory= : The working copy to repair} {--check= : Only this point of the checklist}';

	protected $description = 'Repair the open points of a working copy, so an agent and the lenses can work in it';

	public function handle(): int
	{
		$directory = (string) $this->option('directory');

		if ($directory === '') {
			$this->components->error('No working copy given.');

			return self::FAILURE;
		}

		$repairStateObject = RepairSetupAction::execute($directory, SetupCheckEnum::tryFrom((string) $this->option('check')));

		if ($repairStateObject->failure !== null) {
			$this->components->error($repairStateObject->failure);

			return self::FAILURE;
		}

		$this->components->info('The working copy is repaired as far as DevDock can.');

		return self::SUCCESS;
	}
}
