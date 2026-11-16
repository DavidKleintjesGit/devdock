<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Ticket\Enums\TicketProjectMatchEnum;

/**
 * The project a ticket belongs to, and how that was found.
 */
final class TicketProjectDecisionObject extends ValueObject
{
	public function __construct(
		public readonly TicketProjectMatchEnum $ticketProjectMatch,
		public readonly ?string $project,
	) {
		parent::__construct();
	}
}
