<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A Jira issue that stands in your name.
 */
class TicketEntity extends ValueObject
{
	public function __construct(
		public readonly TicketId $ticketId,
		public readonly string $key,
		public readonly string $summary,
		public readonly ?string $description,
		public readonly ?string $status,
		public readonly ?string $type,
		public readonly ?string $priority,
		public readonly ?string $sprint,
		public readonly ?CarbonImmutable $sprintEndsAt,
		public readonly ?string $url,
		public readonly ?CarbonImmutable $changedAt,
	) {
		parent::__construct();
	}

	/**
	 * Whether the ticket is planned in the sprint that is running now.
	 */
	public function isInSprint(): bool
	{
		return $this->sprint !== null;
	}
}
