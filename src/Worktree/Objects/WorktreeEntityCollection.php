<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class WorktreeEntityCollection extends ValueObjectCollection
{
	protected function __construct(WorktreeEntity ...$worktreeEntities) {}
}
