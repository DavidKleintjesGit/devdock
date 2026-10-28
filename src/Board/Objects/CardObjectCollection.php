<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class CardObjectCollection extends ValueObjectCollection
{
	protected function __construct(CardObject ...$cardObjects)
	{
	}
}
