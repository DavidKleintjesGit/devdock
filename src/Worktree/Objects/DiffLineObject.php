<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * Eén regel uit een wijziging, met het nummer dat hij voor en na de wijziging
 * draagt. Een toegevoegde regel heeft geen oud nummer, een verwijderde geen
 * nieuw.
 */
class DiffLineObject extends ValueObject
{
	public function __construct(
		public readonly string $kind,
		public readonly ?int $oldNumber,
		public readonly ?int $newNumber,
		public readonly string $text,
	) {
		parent::__construct();
	}

	public function sign(): string
	{
		return match ($this->kind) {
			'add' => '+',
			'del' => '−',
			default => '',
		};
	}
}
