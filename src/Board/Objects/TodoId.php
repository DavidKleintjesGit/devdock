<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;

final class TodoId extends ValueObject
{
	public function __construct(
		public readonly ?int $id = null,
		public readonly ?string $uuid = null,
	) {
		parent::__construct();
	}

	/**
	 * The to-do a card key names, such as todo:12; null for a card of anything else.
	 */
	public static function fromBoardKey(string $card): ?self
	{
		return preg_match('/^todo:(\d+)$/', $card, $found) === 1 ? new self(id: (int) $found[1]) : null;
	}
}
