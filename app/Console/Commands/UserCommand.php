<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Account\Actions\SetPasswordAction;
use Src\Account\Objects\PasswordObject;

class UserCommand extends Command
{
	private const MIN_LENGTH = 8;

	protected $signature = 'devdock:user';

	protected $description = 'Set the password DevDock asks for, and log every device out';

	public function handle(): int
	{
		$password = (string) $this->secret('Password');

		if (mb_strlen($password) < self::MIN_LENGTH) {
			$this->components->error(sprintf('Use at least %d characters.', self::MIN_LENGTH));

			return self::FAILURE;
		}

		if ($password !== (string) $this->secret('Same password again')) {
			$this->components->error('The two passwords differ.');

			return self::FAILURE;
		}

		SetPasswordAction::execute(PasswordObject::from(['password' => $password]));

		$this->components->info('Password set. Every device logs in again with it.');

		return self::SUCCESS;
	}
}
