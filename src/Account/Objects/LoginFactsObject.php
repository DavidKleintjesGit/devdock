<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides an attempt to log in: whether there is an account, whether too
 * many attempts went before it, and whether the password matched. The
 * password is not looked at while the attempts are used up; then it is null.
 */
final class LoginFactsObject extends ValueObject
{
	public function __construct(
		public readonly bool $hasAccount,
		public readonly bool $isLockedOut,
		public readonly ?bool $passwordMatches,
	) {
		parent::__construct();
	}
}
