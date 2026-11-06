<?php

declare(strict_types=1);

namespace Src\Account\Actions;

use Illuminate\Support\Facades\Auth;
use Src\Packages\Cqrs\Command;

/**
 * Logs this device out. Other devices stay logged in.
 *
 * @method static void execute()
 */
class LogOutAction extends Command
{
	public function handle(): void
	{
		Auth::guard('web')->logout();
	}
}
