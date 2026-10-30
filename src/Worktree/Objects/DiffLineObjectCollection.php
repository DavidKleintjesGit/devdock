<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class DiffLineObjectCollection extends ValueObjectCollection
{
	protected function __construct(DiffLineObject ...$diffLineObjects) {}
}
