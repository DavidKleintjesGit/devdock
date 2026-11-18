<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What you chose on a round that waits on you: per finding fix, ignore or
 * resolved, why a finding stays as it is, and the message to the agent once
 * you wrote it yourself.
 */
class RoundChoicesObject extends ValueObject
{
	/**
	 * @param  array<int, string>  $choices  the choice per finding id, a FindingChoiceEnum value
	 * @param  array<int, string>  $reasons  why a finding stays as it is, per finding id
	 * @param  ?string  $message  the message you wrote yourself, or null while it follows your choices
	 */
	public function __construct(
		public readonly array $choices,
		public readonly array $reasons,
		public readonly ?string $message = null,
	) {
		parent::__construct();
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			choices: array_map('strval', (array) ($data['choices'] ?? [])),
			reasons: array_map('strval', (array) ($data['reasons'] ?? [])),
			message: isset($data['message']) ? (string) $data['message'] : null,
		);
	}
}
