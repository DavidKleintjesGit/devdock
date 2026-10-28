<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class RuleFileObjectCollection extends ValueObjectCollection
{
	protected function __construct(RuleFileObject ...$ruleFileObjects)
	{
	}
}
