<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where a branch stands when you pick it for a task, which decides what
 * setting up the working copy does with it.
 */
enum BranchPlaceEnum: string
{
	case LOCAL = 'local';
	case WORKTREE = 'worktree';
	case PROJECT = 'project';
	case REMOTE = 'remote';

	/**
	 * The short note next to the branch in the list, if it needs one.
	 */
	public function tag(): ?string
	{
		return match ($this) {
			self::LOCAL => null,
			self::WORKTREE => __('werkkopie bestaat'),
			self::PROJECT => __('werkkopie is het project zelf'),
			self::REMOTE => __('alleen op origin'),
		};
	}
}
