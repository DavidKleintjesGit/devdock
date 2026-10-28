<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A task you put on the board yourself; no ticket, pull request or branch
 * hangs on it.
 */
class TodoObject extends ValueObject
{
	public function __construct(
		public readonly string $title,
		public readonly ?string $note,
		public readonly ?string $project,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			title: (string) $data['title'],
			note: $data['note'] ?? null,
			project: $data['project'] ?? null,
		);
	}
}
