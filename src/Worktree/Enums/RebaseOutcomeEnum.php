<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where a rebase DevDock began ended up.
 */
enum RebaseOutcomeEnum: string
{
	case DONE = 'done';
	// stopped on conflicts and left open, for the agent to finish
	case CONFLICTED = 'conflicted';
	case FAILED = 'failed';
}
