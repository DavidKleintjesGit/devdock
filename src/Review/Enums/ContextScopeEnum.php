<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Which sources the context screen shows for the agent or lens you picked:
 * everything it is given, only what no one else is given, or every source of
 * the task.
 */
enum ContextScopeEnum: string
{
	case GIVEN = 'given';
	case OWN = 'own';
	case ALL = 'all';

	public function label(): string
	{
		return __(match ($this) {
			self::GIVEN => 'Wat hij krijgt',
			self::OWN => 'Alleen hij',
			self::ALL => 'Alle bronnen',
		});
	}
}
