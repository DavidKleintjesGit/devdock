<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where the working copy of a task came from, which decides what cleaning up
 * may take away.
 */
enum WorktreeOriginEnum: string
{
	case MADE = 'made';
	case ADOPTED = 'adopted';
	case PROJECT = 'project';

	/**
	 * Whether DevDock put the folder there, and so may take it away again.
	 */
	public function isOurs(): bool
	{
		return $this === self::MADE;
	}

	public function label(): string
	{
		return __(match ($this) {
			self::MADE => 'gemaakt door DevDock',
			self::ADOPTED => 'stond er al',
			self::PROJECT => 'het project zelf',
		});
	}
}
