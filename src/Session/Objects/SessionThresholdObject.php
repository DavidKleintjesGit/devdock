<?php

declare(strict_types=1);

namespace Src\Session\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * Where the line lies between a session that matters and one that asks to be
 * cleaned up.
 */
class SessionThresholdObject extends ValueObject
{
	public function __construct(
		public readonly int $abandonedUpToPrompts,
		public readonly int $largeFromMegabytes,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			abandonedUpToPrompts: (int) $data['abandoned_up_to'],
			largeFromMegabytes: (int) $data['large_from_mb'],
		);
	}

	public function largeFromBytes(): int
	{
		return $this->largeFromMegabytes * 1024 * 1024;
	}
}
