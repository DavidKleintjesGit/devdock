<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\VerdictEnum;

/**
 * What one lens came back with, read out of its answer.
 */
class VerdictObject extends ValueObject
{
	public function __construct(
		public readonly VerdictEnum $verdict,
		public readonly int $findings,
		public readonly string $report,
	) {
		parent::__construct();
	}
}
