<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\AssessmentVerdictEnum;

/**
 * What the agent said about one finding of a round, as it wrote it in its
 * assessment block.
 */
class AssessmentObject extends ValueObject
{
	public function __construct(
		public readonly ReviewFindingId $reviewFindingId,
		public readonly AssessmentVerdictEnum $verdict,
		public readonly string $proposal,
	) {
		parent::__construct();
	}
}
