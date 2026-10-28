<?php

declare(strict_types=1);

namespace Src\Session\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One turn from a session file: what was said and at what time.
 */
class TranscriptTurnObject extends ValueObject
{
	public function __construct(
		public readonly string $who,
		public readonly string $text,
		public readonly string $at,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			who: (string) $data['who'],
			text: (string) $data['text'],
			at: (string) $data['at'],
		);
	}
}
