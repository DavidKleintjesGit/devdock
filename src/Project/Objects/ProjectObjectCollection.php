<?php

declare(strict_types=1);

namespace Src\Project\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class ProjectObjectCollection extends ValueObjectCollection
{
	protected function __construct(ProjectObject ...$projectObjects)
	{
	}
}
