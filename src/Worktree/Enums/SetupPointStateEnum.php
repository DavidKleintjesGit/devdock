<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where one point of the checklist stands while DevDock repairs, or after it did.
 */
enum SetupPointStateEnum: string
{
	case DONE = 'done';
	case BUSY = 'busy';
	case QUEUED = 'queued';
	case WAITING = 'waiting';
	case FAILED = 'failed';
	case OPEN = 'open';

	/**
	 * The short state a group of points shows in its header, for the most pressing point in it.
	 */
	public function groupLabel(): string
	{
		return __(match ($this) {
			self::DONE => 'in orde',
			self::BUSY => 'bezig',
			self::QUEUED, self::WAITING => 'wacht nog',
			self::FAILED => 'niet gelukt',
			self::OPEN => 'open',
		});
	}

	/**
	 * How pressing this state is in a group: the highest decides the header of the group.
	 */
	public function weight(): int
	{
		return match ($this) {
			self::FAILED => 5,
			self::BUSY => 4,
			self::OPEN => 3,
			self::WAITING => 2,
			self::QUEUED => 1,
			self::DONE => 0,
		};
	}
}
