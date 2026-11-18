<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides whether cleaning up a task loses work: the files not committed
 * in its working copy, the commits that no branch on origin holds, and
 * whether losing them was accepted.
 */
final class CleanUpFactsObject extends ValueObject
{
	public function __construct(
		public readonly int $uncommittedFiles,
		public readonly int $unpushedCommits,
		public readonly bool $discardsWork,
	) {
		parent::__construct();
	}
}
