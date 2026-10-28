<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class TicketEntityCollection extends ValueObjectCollection
{
	protected function __construct(TicketEntity ...$ticketEntities)
	{
	}
}
