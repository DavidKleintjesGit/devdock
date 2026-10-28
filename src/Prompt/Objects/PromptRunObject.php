<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The order to let a prompt loose on a task.
 */
class PromptRunObject extends ValueObject
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $project,
		public readonly ?string $branch,
		public readonly ?string $ticketKey,
		public readonly ?string $sessionUuid,
		public readonly string $prompt,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			card: (string) $data['card'],
			project: $data['project'] ?? null,
			branch: $data['branch'] ?? null,
			ticketKey: $data['ticket_key'] ?? null,
			sessionUuid: $data['session_uuid'] ?? null,
			prompt: (string) $data['prompt'],
		);
	}
}
