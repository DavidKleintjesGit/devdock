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
}
