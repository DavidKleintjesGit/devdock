<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A commit message as the session that made the changes wrote it: one line
 * that names the change, and the lines underneath that explain it.
 */
class CommitMessageObject extends ValueObject
{
	public function __construct(
		public readonly string $title,
		public readonly string $body,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			title: trim((string) $data['title']),
			body: trim((string) ($data['body'] ?? '')),
		);
	}

	public function isEmpty(): bool
	{
		return $this->title === '';
	}
}
