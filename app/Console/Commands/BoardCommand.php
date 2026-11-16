<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Board\Actions\GetBoardAction;

/**
 * Builds the board again on the side, after work that ran by itself changed
 * it, so no page waits for it.
 */
class BoardCommand extends Command
{
	protected $signature = 'devdock:board';

	protected $description = 'Build the board again on the side';

	public function handle(): int
	{
		GetBoardAction::execute(true);

		return self::SUCCESS;
	}
}
