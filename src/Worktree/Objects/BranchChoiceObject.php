<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\BranchPlaceEnum;

/**
 * One branch you can set a task up on, with the folder it already stands in
 * when there is one.
 */
class BranchChoiceObject extends ValueObject
{
	public function __construct(
		public readonly string $name,
		public readonly BranchPlaceEnum $place,
		public readonly ?string $path,
		public readonly bool $matchesTicket,
	) {
		parent::__construct();
	}
}
