<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The one person DevDock opens for. Every device that logs in, logs in as this account.
 */
final class AccountEntity extends ValueObject
{
	public function __construct(
		public readonly AccountId $accountId,
		public readonly string $name,
	) {
		parent::__construct();
	}
}
