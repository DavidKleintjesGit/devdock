<?php

declare(strict_types=1);

namespace Src\Ticket\Enums;

/**
 * How a ticket without a branch or pull request found its project: through a
 * label you tied to a project yourself, through a label that carries the
 * project's name, or not at all.
 */
enum TicketProjectMatchEnum: string
{
	case CONFIGURED = 'configured';
	case BY_LABEL = 'by_label';
	case AMBIGUOUS = 'ambiguous';
	case NONE = 'none';

	public function label(): string
	{
		return __(match ($this) {
			self::CONFIGURED => 'Gekoppeld via een label uit de instellingen',
			self::BY_LABEL => 'Gekoppeld via het Jira-label',
			self::AMBIGUOUS => 'Het label past bij meer dan één project',
			self::NONE => 'Zonder project',
		});
	}
}
