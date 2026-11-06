<?php

declare(strict_types=1);

namespace Src\Account\Actions;

use Illuminate\Support\Facades\Auth;
use Src\Account\Contracts\AccountRepositoryContract;
use Src\Packages\Cqrs\Command;

/**
 * Logs every device out, this one included: for a phone that went missing.
 * A remembered device has to log in again too.
 *
 * @method static void execute()
 */
class LogOutEverywhereAction extends Command
{
	public function handle(AccountRepositoryContract $accountRepository): void
	{
		$accountEntity = $accountRepository->getOwner();

		Auth::guard('web')->logout();

		if ($accountEntity !== null) {
			$accountRepository->forgetDevices($accountEntity->accountId);
		}
	}
}
