<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class PromptRunEntityCollection extends ValueObjectCollection
{
	protected function __construct(PromptRunEntity ...$promptRunEntities)
	{
	}
}
