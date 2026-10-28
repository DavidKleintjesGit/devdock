<?php

declare(strict_types=1);

namespace Src\Fetch\Enums;

/**
 * The sources the board is built from.
 */
enum FetchSourceEnum: string
{
	case PROJECTS = 'projects';
	case SESSIONS = 'sessions';
	case RULES = 'rules';
	case SKILLS = 'skills';
	case TICKETS = 'tickets';
	case PULL_REQUESTS = 'pull-requests';

	public function label(): string
	{
		return __(match ($this) {
			self::PROJECTS => 'Projecten',
			self::SESSIONS => 'Sessies',
			self::RULES => 'Regels',
			self::SKILLS => 'Skills',
			self::TICKETS => 'Tickets',
			self::PULL_REQUESTS => 'Pull requests',
		});
	}
}
