<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Work on the working copy of a card that takes longer than a click may wait,
 * and so runs in a process of its own.
 */
enum WorkingCopyTaskEnum: string
{
	case LINK = 'link';

	/**
	 * What the card says while the task runs.
	 */
	public function busyLabel(): string
	{
		return __(match ($this) {
			self::LINK => 'DevDock maakt de werkkopie',
		});
	}

	/**
	 * What the card says when the task failed.
	 */
	public function failedLabel(): string
	{
		return __(match ($this) {
			self::LINK => 'De werkkopie kon niet gemaakt worden',
		});
	}
}
