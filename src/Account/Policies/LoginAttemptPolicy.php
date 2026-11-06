<?php

declare(strict_types=1);

namespace Src\Account\Policies;

use Src\Account\Enums\LoginOutcomeEnum;
use Src\Account\Objects\LoginFactsObject;

/**
 * The rule for an attempt to log in: without an account there is nothing to
 * log in to, and once the attempts are used up even the right password waits.
 * Only then does the password decide.
 */
class LoginAttemptPolicy
{
	public function decide(LoginFactsObject $loginFactsObject): LoginOutcomeEnum
	{
		if (! $loginFactsObject->hasAccount) {
			return LoginOutcomeEnum::NO_ACCOUNT;
		}

		if ($loginFactsObject->isLockedOut) {
			return LoginOutcomeEnum::LOCKED_OUT;
		}

		return $loginFactsObject->passwordMatches === true
			? LoginOutcomeEnum::LOGGED_IN
			: LoginOutcomeEnum::WRONG_PASSWORD;
	}
}
