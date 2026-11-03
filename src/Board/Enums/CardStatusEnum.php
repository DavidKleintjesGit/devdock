<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * Who a task waits on, which is the marker in front of the status line.
 */
enum CardStatusEnum: string
{
	case YOURS = 'yours';
	case BUSY = 'busy';
	case QUIET = 'quiet';
	case CONFLICT = 'conflict';
	case APPROVED = 'approved';
	case WAITING = 'waiting';

	/**
	 * Whether the next step is yours: a conflict and an approved pull request
	 * wait on you as much as a plain turn does.
	 */
	public function isYours(): bool
	{
		return in_array($this, [self::YOURS, self::CONFLICT, self::APPROVED], true);
	}
}
