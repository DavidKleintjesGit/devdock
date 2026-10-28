<?php

declare(strict_types=1);

namespace Src\Ticket\Contracts;

use Src\Ticket\Objects\TicketEntity;
use Src\Ticket\Objects\TicketEntityCollection;
use Src\Ticket\Objects\TicketObject;
use Src\Ticket\Objects\TicketObjectCollection;

interface TicketRepositoryContract
{
	public function create(TicketObject $ticketObject): TicketEntity;

	public function getAll(): TicketEntityCollection;

	public function getByKey(string $key): ?TicketEntity;

	/**
	 * Brings the stored tickets in line with what Jira returned. An empty list
	 * leaves everything standing: Jira was unreachable then, not empty.
	 */
	public function replaceAll(TicketObjectCollection $ticketObjects): TicketEntityCollection;
}
