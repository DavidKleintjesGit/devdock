<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class RuleFileEntityCollection extends ValueObjectCollection
{
	protected function __construct(RuleFileEntity ...$ruleFileEntities)
	{
	}
}
