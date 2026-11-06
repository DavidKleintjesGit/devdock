<?php

declare(strict_types=1);

namespace Src\Account\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Src\Account\Contracts\AccountRepositoryContract;
use Src\Account\Enums\LoginOutcomeEnum;
use Src\Account\Objects\LoginFactsObject;
use Src\Account\Objects\LoginObject;
use Src\Account\Objects\LoginResultObject;
use Src\Account\Policies\LoginAttemptPolicy;
use Src\Packages\Cqrs\Command;

/**
 * Logs this device in as the account when the password matches. Every wrong
 * password counts towards a wait of a minute; a right one clears the count.
 *
 * @method static LoginResultObject execute(LoginObject $loginObject)
 */
class LogInAction extends Command
{
	public const LIMITER_KEY = 'login';

	public const MAX_ATTEMPTS = 5;

	public const WAIT_SECONDS = 60;

	public function __construct(
		public readonly LoginObject $loginObject,
	) {}

	public function handle(AccountRepositoryContract $accountRepository): LoginResultObject
	{
		$accountEntity = $accountRepository->getOwner();
		$isLockedOut = RateLimiter::tooManyAttempts(self::LIMITER_KEY, self::MAX_ATTEMPTS);

		$loginOutcome = resolve(LoginAttemptPolicy::class)->decide(new LoginFactsObject(
			hasAccount: $accountEntity !== null,
			isLockedOut: $isLockedOut,
			passwordMatches: $accountEntity === null || $isLockedOut
				? null
				: $accountRepository->passwordMatches($accountEntity->accountId, $this->loginObject->password),
		));

		if ($loginOutcome === LoginOutcomeEnum::LOGGED_IN) {
			RateLimiter::clear(self::LIMITER_KEY);
			Auth::loginUsingId($accountEntity->accountId->id, $this->loginObject->remembers);
		}

		if ($loginOutcome === LoginOutcomeEnum::WRONG_PASSWORD) {
			RateLimiter::hit(self::LIMITER_KEY, self::WAIT_SECONDS);
		}

		return new LoginResultObject(
			loginOutcome: $loginOutcome,
			attemptsLeft: RateLimiter::remaining(self::LIMITER_KEY, self::MAX_ATTEMPTS),
		);
	}
}
