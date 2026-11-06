<?php

declare(strict_types=1);

namespace Src\Account\Enums;

/**
 * How an attempt to log in ended.
 */
enum LoginOutcomeEnum: string
{
	case LOGGED_IN = 'logged_in';
	case WRONG_PASSWORD = 'wrong_password';
	case LOCKED_OUT = 'locked_out';
	case NO_ACCOUNT = 'no_account';

	public function label(): string
	{
		return __(match ($this) {
			self::LOGGED_IN => 'Ingelogd.',
			self::WRONG_PASSWORD => 'Dat wachtwoord klopt niet.',
			self::LOCKED_OUT => 'Te vaak geprobeerd.',
			self::NO_ACCOUNT => 'Nog geen gebruiker.',
		});
	}
}
