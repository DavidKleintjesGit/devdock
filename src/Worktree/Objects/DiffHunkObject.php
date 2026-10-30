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
	) {
		parent::__construct();
	}
}
