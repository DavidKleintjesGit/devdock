<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides the branch a working copy merges into: the target of its open
 * pull request, and the target chosen for it by hand.
 */
final class TargetFactsObject extends ValueObject
{
	public function __construct(
		public readonly ?string $pullRequestTarget,
		public readonly ?string $chosenTarget,
	) {
		parent::__construct();
	}
}
