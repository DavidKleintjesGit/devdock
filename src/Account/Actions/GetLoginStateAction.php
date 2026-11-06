<?php

declare(strict_types=1);

namespace Src\Account\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Src\Account\Contracts\AccountRepositoryContract;
use Src\Account\Objects\LoginStateObject;
use Src\Packages\Cqrs\Command;

/**
 * Reads what the login page starts with: whether there is an account, how long
 * the wait after too many attempts still lasts, and which card the address you
 * came from opens.
 *
 * @method static LoginStateObject execute(?string $returnUrl)
 */
class GetLoginStateAction extends Command
{
	public function __construct(
		public readonly ?string $returnUrl,
	) {}

	public function handle(AccountRepositoryContract $accountRepository): LoginStateObject
	{
		parse_str((string) parse_url((string) $this->returnUrl, PHP_URL_QUERY), $query);

		$isLockedOut = RateLimiter::tooManyAttempts(LogInAction::LIMITER_KEY, LogInAction::MAX_ATTEMPTS);

		return new LoginStateObject(
			hasAccount: $accountRepository->getOwner() !== null,
			secondsLocked: $isLockedOut ? RateLimiter::availableIn(LogInAction::LIMITER_KEY) : 0,
			resumeCard: filled($query['card'] ?? null) ? (string) $query['card'] : null,
			resumeTab: filled($query['tab'] ?? null) ? (string) $query['tab'] : null,
		);
	}
}
