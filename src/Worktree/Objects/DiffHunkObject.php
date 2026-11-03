<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * Eén stuk uit een wijziging: de regel waarmee git hem aankondigt, en de
 * regels eronder.
 */
class DiffHunkObject extends ValueObject
{
	public function __construct(
		public readonly string $header,
		public readonly DiffLineObjectCollection $lines,
		public readonly int $oldStart = 0,
		public readonly int $oldCount = 0,
		// the lines of the file between the piece before and this one, which the diff leaves out
		public readonly int $hiddenBefore = 0,
	) {
		parent::__construct();
	}

	/**
	 * The old line right after this piece.
	 */
	public function oldEnd(): int
	{
		return $this->oldStart + $this->oldCount;
	}
}
