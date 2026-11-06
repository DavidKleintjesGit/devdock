<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A new password for the account, as typed on the laptop.
 */
final class PasswordObject extends ValueObject
{
	public function __construct(
		public readonly string $password,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			password: (string) $data['password'],
		);
	}
}
