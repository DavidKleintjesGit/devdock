<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides the verdict of a lens: whether its answer could be read, and
 * how many of its findings on the card are still open.
 */
class LensFindingsFactsObject extends ValueObject
{
	public function __construct(
		public readonly bool $isReadable,
		public readonly int $openFindings,
	) {
		parent::__construct();
	}
}
