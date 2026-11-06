<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\SetupPointStateEnum;

/**
 * One point of the checklist with where it stands in the repair, and the line shown under it.
 */
class SetupPointObject extends ValueObject
{
	public function __construct(
		public readonly SetupCheckObject $setupCheck,
		public readonly SetupPointStateEnum $state,
		public readonly string $text,
		// why the point failed in the last repair
		public readonly ?string $reason = null,
		// the names of the open points that wait on this one, when it failed
		public readonly string $waitingPoints = '',
	) {
		parent::__construct();
	}
}
