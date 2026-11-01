<?php

declare(strict_types=1);

namespace Src\Board\Enums;

use Src\Board\Objects\CardObject;

/**
 * The three things that make a task jump the queue.
 */
enum BoardSignalEnum: string
{
	case YOUR_TURN = 'your-turn';
	case HIGH_PRIORITY = 'high-priority';
	case STALLED = 'stalled';

	public function label(): string
	{
		return __(match ($this) {
			self::YOUR_TURN => 'Jouw beurt',
			self::HIGH_PRIORITY => 'Hoge prioriteit',
			self::STALLED => 'Stilgevallen',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::YOUR_TURN => 'teal',
			self::HIGH_PRIORITY => 'red',
			self::STALLED => 'gray',
		};
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
			self::YOUR_TURN => $cardObject->isYourTurn(),
			self::HIGH_PRIORITY => $cardObject->isHighPriority(),
			self::STALLED => $cardObject->isStale,
		};
	}
}
