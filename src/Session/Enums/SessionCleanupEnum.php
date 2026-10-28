<?php

declare(strict_types=1);

namespace Src\Session\Enums;

/**
 * The sessions that ask to be cleaned up.
 */
enum SessionCleanupEnum: string
{
	case ABANDONED = 'afgebroken';
	case LARGE = 'groot';
	case UNNAMED = 'zonder';
	case TICKET_MISMATCH = 'mismatch';

	public function label(): string
	{
		return __(match ($this) {
			self::ABANDONED => 'Afgebroken',
			self::LARGE => 'Groot',
			self::UNNAMED => 'Zonder naam',
			self::TICKET_MISMATCH => 'Naam ≠ ticket',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::TICKET_MISMATCH => 'red',
			default => 'amber',
		};
	}
}
