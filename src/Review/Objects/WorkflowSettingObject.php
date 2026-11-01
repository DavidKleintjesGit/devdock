<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Review\Enums\AfterRoundsEnum;

/**
 * How the rounds run in a project: the standard, or what the project set for
 * itself, with what it adds to the way of working.
 */
class WorkflowSettingObject extends ValueObject
{
	public function __construct(
		public readonly int $maxRounds,
		public readonly AfterRoundsEnum $afterRounds,
		public readonly ?string $context,
		public readonly bool $hasOwnThreshold,
	) {
		parent::__construct();
	}
}
