<?php

declare(strict_types=1);

namespace Src\Ticket\Contracts;

use Src\Ticket\Objects\TicketObjectCollection;

interface TicketSourceRepositoryContract
{
	/**
	 * Whether an address, an account and a token are there to fetch with.
	 */
	public function isConfigured(): bool;

	/**
	 * Every open issue that stands in the name of the account behind the token.
	 */
	public function getAssigned(): TicketObjectCollection;
}
