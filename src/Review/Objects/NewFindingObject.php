<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A finding a lens wrote down for the first time: where it is and what is wrong.
 */
class NewFindingObject extends ValueObject
{
	public function __construct(
		public readonly ?string $location,
		public readonly string $text,
	) {
		parent::__construct();
	}
}
