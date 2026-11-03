<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\LinkStepEnum;

/**
 * What one card needs to get a complete working copy, and what stands in the
 * way when it cannot be done for it.
 */
class LinkPlanObject extends ValueObject
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $project,
		public readonly ?string $branch,
		public readonly LinkStepEnum $linkStep,
		public readonly ?string $path,
		public readonly string $detail,
	) {
		parent::__construct();
	}
}
