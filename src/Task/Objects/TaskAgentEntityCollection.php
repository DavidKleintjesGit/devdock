<?php

declare(strict_types=1);

namespace Src\Task\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class TaskAgentEntityCollection extends ValueObjectCollection
{
	protected function __construct(TaskAgentEntity ...$taskAgentEntities) {}
}
