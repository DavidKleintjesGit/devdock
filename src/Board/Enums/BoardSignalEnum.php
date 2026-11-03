<?php

declare(strict_types=1);

namespace Src\Board\Enums;

use Src\Board\Objects\CardObject;

/**
 * The things that make a task jump the queue.
 */
enum BoardSignalEnum: string
{
	case UNREAD = 'unread';
	case YOUR_TURN = 'your-turn';
	case CONFLICT = 'conflict';
	case HIGH_PRIORITY = 'high-priority';
	case STALLED = 'stalled';

	public function label(): string
	{
		return __(match ($this) {
			self::UNREAD => 'Ongelezen',
			self::YOUR_TURN => 'Jouw beurt',
			self::CONFLICT => 'Conflict',
			self::HIGH_PRIORITY => 'Hoge prioriteit',
			self::STALLED => 'Stilgevallen',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::UNREAD, self::YOUR_TURN => 'teal',
			self::CONFLICT => 'red',
			self::HIGH_PRIORITY => 'red',
			self::STALLED => 'gray',
		};
	}

	/**
	 * Whether the filter shows its count as a badge in front, the way a card shows it.
	 */
	public function hasCountBadge(): bool
	{
		return $this === self::UNREAD;
	}

	/**
	 * Whether the filter carries the red triangle of a high priority instead
	 * of a dot.
	 */
	public function hasPriorityMark(): bool
	{
		return $this === self::HIGH_PRIORITY;
	}

	public function matches(CardObject $cardObject): bool
	{
		return match ($this) {
			self::UNREAD => $cardObject->hasUnread(),
			self::YOUR_TURN => $cardObject->isYourTurn(),
			self::CONFLICT => $cardObject->hasConflict(),
			self::HIGH_PRIORITY => $cardObject->isHighPriority(),
			self::STALLED => $cardObject->isStale,
		};
	}
}
