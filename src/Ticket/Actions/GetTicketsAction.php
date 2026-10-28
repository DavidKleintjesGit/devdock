<?php

declare(strict_types=1);

namespace Src\Ticket\Actions;

use Src\Packages\Cqrs\Command;
use Src\Ticket\Contracts\TicketRepositoryContract;
use Src\Ticket\Objects\TicketEntityCollection;

/**
 * @method static TicketEntityCollection execute()
 */
class GetTicketsAction extends Command
{
	public function handle(TicketRepositoryContract $ticketRepository): TicketEntityCollection
	{
		return $ticketRepository->getAll();
	}
}
