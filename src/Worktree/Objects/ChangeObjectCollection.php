<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class ChangeObjectCollection extends ValueObjectCollection
{
	protected function __construct(ChangeObject ...$changeObjects) {}
}
