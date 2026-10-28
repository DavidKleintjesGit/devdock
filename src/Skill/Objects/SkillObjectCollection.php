<?php

declare(strict_types=1);

namespace Src\Skill\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class SkillObjectCollection extends ValueObjectCollection
{
	protected function __construct(SkillObject ...$skillObjects)
	{
	}
}
