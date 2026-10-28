<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class TodoEntityCollection extends ValueObjectCollection
{
	protected function __construct(TodoEntity ...$todoEntities)
	{
	}
}
