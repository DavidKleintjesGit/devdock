<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * Where an unread notification on a card comes from, named after the tab of
 * the card that shows it: opening that tab reads it.
 */
enum UnreadSourceEnum: string
{
	case AGENT = 'Agent';
	case REVIEW = 'Review';
}
