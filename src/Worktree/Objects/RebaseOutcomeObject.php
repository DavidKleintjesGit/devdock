<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\RebaseOutcomeEnum;

/**
 * What came of a rebase DevDock began: done, open on conflicts in the named
 * files, or failed with the reason.
 */
class RebaseOutcomeObject extends ValueObject
{
	/**
	 * @param  list<string>  $conflictedFiles
	 */
	public function __construct(
		public readonly RebaseOutcomeEnum $outcome,
		public readonly string $targetBranch,
		public readonly array $conflictedFiles = [],
		public readonly ?string $failure = null,
	) {
		parent::__construct();
	}
}
