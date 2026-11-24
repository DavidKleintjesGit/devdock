<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Worktree\Actions\ReadQueuedWorkingCopiesAction;

/**
 * Reads the working copies in line again on the side, while the pages go on with what they read last time.
 */
class ReadWorkingCopiesCommand extends Command
{
	protected $signature = 'devdock:read-working-copies';

	protected $description = 'Read the working copies in line from git again, one after the other';

	public function handle(): int
	{
		ReadQueuedWorkingCopiesAction::execute();

		return self::SUCCESS;
	}
}
