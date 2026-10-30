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
		public readonly ?int $reviewRoundId,
		public readonly ?string $project,
		public readonly ?string $branch,
		public readonly ?string $directory,
		public readonly ?string $ticketKey,
		public readonly ?string $sessionUuid,
		public readonly bool $claimsSession,
		public readonly string $prompt,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			card: (string) $data['card'],
			reviewRoundId: isset($data['review_round_id']) ? (int) $data['review_round_id'] : null,
			project: $data['project'] ?? null,
			branch: $data['branch'] ?? null,
			directory: $data['directory'] ?? null,
			ticketKey: $data['ticket_key'] ?? null,
			sessionUuid: $data['session_uuid'] ?? null,
			claimsSession: (bool) ($data['claims_session'] ?? false),
			prompt: (string) $data['prompt'],
		);
	}
}
