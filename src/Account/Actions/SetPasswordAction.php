<?php

declare(strict_types=1);

namespace Src\Account\Actions;

use Src\Account\Contracts\AccountRepositoryContract;
use Src\Account\Objects\AccountEntity;
use Src\Account\Objects\PasswordObject;
use Src\Packages\Cqrs\Command;

/**
 * Gives the account a new password, and makes the account when there is none
 * yet. Every device that was logged in has to log in again with it.
 *
 * @method static AccountEntity execute(PasswordObject $passwordObject)
 */
class SetPasswordAction extends Command
{
	public function __construct(
		public readonly PasswordObject $passwordObject,
	) {}

	public function handle(AccountRepositoryContract $accountRepository): AccountEntity
	{
		$accountEntity = $accountRepository->savePassword($this->passwordObject);

		$accountRepository->forgetDevices($accountEntity->accountId);

		return $accountEntity;
	}
}
