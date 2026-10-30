<?php

declare(strict_types=1);

namespace Src\Task\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class TaskLinkEntityCollection extends ValueObjectCollection
{
	protected function __construct(TaskLinkEntity ...$taskLinkEntities) {}
}
