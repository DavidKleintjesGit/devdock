<?php

declare(strict_types=1);

namespace Src\Account\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * An attempt to log in: the password, and whether this device stays logged in
 * until you log out yourself.
 */
final class LoginObject extends ValueObject
{
	public function __construct(
		public readonly string $password,
		public readonly bool $remembers,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			password: (string) $data['password'],
			remembers: (bool) ($data['remember'] ?? false),
		);
	}
}
