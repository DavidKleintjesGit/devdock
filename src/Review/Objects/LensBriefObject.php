<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The longer assignment a lens may carry next to its one-line description.
 */
class LensBriefObject extends ValueObject
{
	public function __construct(
		public readonly string $text,
	) {
		parent::__construct();
	}
}
