<?php

declare(strict_types=1);

namespace Src\Account\Contracts;

use Src\Account\Objects\AccountEntity;
use Src\Account\Objects\AccountId;
use Src\Account\Objects\PasswordObject;

interface AccountRepositoryContract
{
	public function getOwner(): ?AccountEntity;

	public function passwordMatches(AccountId $accountId, string $password): bool;

	/**
	 * Sets the password of the one account, and creates that account when there is none yet.
	 */
	public function savePassword(PasswordObject $passwordObject): AccountEntity;

	/**
	 * Forgets every device that logged in as this account: the remembered ones
	 * and the open sessions.
	 */
	public function forgetDevices(AccountId $accountId): void;
}
