<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Where the branch a working copy merges into comes from.
 */
enum TargetSourceEnum: string
{
	case PULL_REQUEST = 'pull_request';
	case CHOSEN = 'chosen';
	case MAIN_LINE = 'main_line';
}
