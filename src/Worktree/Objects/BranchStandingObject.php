<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * How a branch stands against the main line on origin: how far behind it is,
 * and which files would clash if the two came together now.
 */
class BranchStandingObject extends ValueObject
{
	public function __construct(
		public readonly int $behind,
		public readonly array $conflictedFiles,
		public readonly string $mainBranch,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			behind: (int) ($data['behind'] ?? 0),
			conflictedFiles: array_values((array) ($data['conflicted_files'] ?? [])),
			mainBranch: (string) ($data['main_branch'] ?? 'master'),
		);
	}

	public function hasConflict(): bool
	{
		return $this->conflictedFiles !== [];
	}
}
