<?php

declare(strict_types=1);

namespace Src\Ticket\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A Jira issue as the search returned it.
 */
class TicketObject extends ValueObject
{
	public function __construct(
		public readonly string $key,
		public readonly string $summary,
		public readonly ?string $description,
		public readonly ?string $status,
		public readonly ?string $type,
		public readonly ?string $priority,
		public readonly ?string $url,
		public readonly ?CarbonImmutable $changedAt,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		$fields = $data['fields'] ?? [];

		return new self(
			key: (string) $data['key'],
			summary: (string) ($fields['summary'] ?? $data['key']),
			description: $data['description'] ?? null,
			status: $fields['status']['name'] ?? null,
			type: $fields['issuetype']['name'] ?? null,
			priority: $fields['priority']['name'] ?? null,
			url: $data['url'] ?? null,
			changedAt: isset($fields['updated']) ? CarbonImmutable::parse($fields['updated']) : null,
		);
	}
}
