<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where one point of the checklist of a working copy stands.
 */
enum CheckStateEnum: string
{
	case READY = 'ready';
	case OPEN = 'open';
	case WAITING = 'waiting';
	case UNCHECKED = 'unchecked';

	/**
	 * The colour of the dot in front of the point.
	 */
	public function tone(): string
	{
		return match ($this) {
			self::READY => 'green',
			self::OPEN => 'red',
			self::WAITING, self::UNCHECKED => 'quiet',
		};
	}
}
