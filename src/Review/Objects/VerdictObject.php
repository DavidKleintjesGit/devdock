<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;

/**
 * What one lens came back with, read out of its answer: its verdict, what
 * became of the findings it had before, and the ones it found now.
 */
class VerdictObject extends ValueObject
{
	/**
	 * @param  array<int, FindingStatusEnum>  $earlierFindings  by the number of the finding
	 * @param  list<NewFindingObject>  $newFindings
	 */
	public function __construct(
		public readonly VerdictEnum $verdict,
		public readonly int $findings,
		public readonly string $report,
		public readonly bool $isReadable = true,
		public readonly array $earlierFindings = [],
		public readonly array $newFindings = [],
	) {
		parent::__construct();
	}
}
