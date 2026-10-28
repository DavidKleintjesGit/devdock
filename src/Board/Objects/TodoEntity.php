<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A task you put on the board yourself.
 */
class TodoEntity extends ValueObject
{
	public function __construct(
		public readonly TodoId $todoId,
		public readonly string $title,
		public readonly ?string $note,
		public readonly ?string $project,
		public readonly ?CarbonImmutable $doneAt,
		public readonly ?CarbonImmutable $changedAt,
	) {
		parent::__construct();
	}

	public function isDone(): bool
	{
		return $this->doneAt !== null;
	}

	/**
	 * The key this to-do stands under on the board.
	 */
	public function boardKey(): string
	{
		return 'todo:' . $this->todoId->id;
	}
}
