<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Account\Enums\LoginOutcomeEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * How an attempt to log in ended, with how many attempts are left before a
 * wait.
 */
final class LoginResultObject extends ValueObject
{
	public function __construct(
		public readonly LoginOutcomeEnum $loginOutcome,
		public readonly int $attemptsLeft,
	) {
		parent::__construct();
	}

	/**
	 * The sentence under the password field after a wrong password.
	 */
	public function message(): string
	{
		if ($this->loginOutcome !== LoginOutcomeEnum::WRONG_PASSWORD || $this->attemptsLeft <= 0) {
			return $this->loginOutcome->label();
		}

		return $this->loginOutcome->label() . ' ' . trans_choice(
			'Na nog :count poging moet je een minuut wachten.|Na nog :count pogingen moet je een minuut wachten.',
			$this->attemptsLeft,
			['count' => $this->attemptsLeft],
		);
	}
}
