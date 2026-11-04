<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One step in a stack of pull requests: a branch, the branch it merges into,
 * and how many commits of that target it does not hold yet.
 */
class BranchLinkObject extends ValueObject
{
	public function __construct(
		public readonly string $branch,
		public readonly string $target,
		public readonly int $behind,
		// the key of the task this branch is, to open its card
		public readonly ?string $cardKey,
	) {
		parent::__construct();
	}
}
