<?php

declare(strict_types=1);

namespace Src\Review\Policies;

use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensFindingsFactsObject;

/**
 * The rule for the verdict of a lens: it fails while one of its findings on
 * the card is still open, or when its answer could not be read. A finding you
 * ignored or a lens saw fixed does not hold it up.
 */
class LensVerdictPolicy
{
	public function decide(LensFindingsFactsObject $lensFindingsFactsObject): VerdictEnum
	{
		return ! $lensFindingsFactsObject->isReadable || $lensFindingsFactsObject->openFindings > 0
			? VerdictEnum::FAIL
			: VerdictEnum::PASS;
	}
}
