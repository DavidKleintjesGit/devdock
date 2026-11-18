<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class CardArchiveEntityCollection extends ValueObjectCollection
{
	protected function __construct(CardArchiveEntity ...$cardArchiveEntities)
	{
	}
}
