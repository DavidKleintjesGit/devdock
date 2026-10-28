<?php

declare(strict_types=1);

namespace Src\Board\Enums;

use Src\Board\Objects\CardObject;

/**
 * The three things that make a task jump the queue.
 */
enum BoardSignalEnum: string
{
	case NEEDS_YOU = 'needs-you';
	case HIGH_PRIORITY = 'high-priority';
	case STALLED = 'stalled';

	public function label(): string
	{
		return __(match ($this) {
			self::NEEDS_YOU => 'Needs you',
			self::HIGH_PRIORITY => 'High priority',
			self::STALLED => 'Stalled',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::NEEDS_YOU => 'teal',
			self::HIGH_PRIORITY => 'red',
			self::STALLED => 'amber',
		};
	}

	public function matches(CardObject $cardObject): bool
	{
		return match ($this) {
			self::NEEDS_YOU => $cardObject->column->waitsOnYou(),
			self::HIGH_PRIORITY => $cardObject->isHighPriority(),
			self::STALLED => $cardObject->isStale,
		};
	}
}
