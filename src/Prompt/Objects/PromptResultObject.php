<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What Claude Code sent back on a prompt.
 */
class PromptResultObject extends ValueObject
{
	public function __construct(
		public readonly bool $hasFailed,
		public readonly ?string $sessionUuid,
		public readonly ?string $output,
		public readonly ?string $message,
		public readonly ?float $cost,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		$hasFailed = (bool) ($data['is_error'] ?? false);

		return new self(
			hasFailed: $hasFailed,
			sessionUuid: $data['session_id'] ?? null,
			output: $hasFailed ? null : ($data['result'] ?? null),
			message: $hasFailed ? ($data['result'] ?? null) : null,
			cost: isset($data['total_cost_usd']) ? (float) $data['total_cost_usd'] : null,
		);
	}
}
