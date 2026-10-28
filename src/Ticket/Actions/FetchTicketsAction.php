<?php

declare(strict_types=1);

namespace Src\Ticket\Actions;

use Src\Packages\Cqrs\Command;
use Src\Ticket\Contracts\TicketRepositoryContract;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;
use Src\Ticket\Objects\TicketEntityCollection;

/**
 * Fetches the issues that stand in your name in Jira and brings the stored
 * tickets in line with them.
 *
 * @method static TicketEntityCollection execute()
 */
class FetchTicketsAction extends Command
{
	public function handle(
		TicketSourceRepositoryContract $ticketSourceRepository,
		TicketRepositoryContract $ticketRepository,
	): TicketEntityCollection {
		if (! $ticketSourceRepository->isConfigured()) {
			return $ticketRepository->getAll();
		}

		return $ticketRepository->replaceAll($ticketSourceRepository->getAssigned());
	}
}
