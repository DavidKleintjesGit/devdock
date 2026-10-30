<?php

declare(strict_types=1);

namespace Src\Task\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Task\Enums\TaskLinkKindEnum;

/**
 * One tie between a task and something outside it. A task may gather several
 * of a kind, but works with the one that is active.
 */
class TaskLinkEntity extends ValueObject
{
	public function __construct(
		public readonly TaskLinkKindEnum $kind,
		public readonly string $value,
		public readonly bool $isActive,
	) {
		parent::__construct();
	}
}
