<?php

declare(strict_types=1);

namespace Src\Access\Enums;

/**
 * What the guard decides about a shell command.
 */
enum ShellCommandEnum: string
{
	case ALLOWED = 'allowed';
	case WRITES_GIT = 'writes_git';
	case WRITES_REMOTE = 'writes_remote';
}
