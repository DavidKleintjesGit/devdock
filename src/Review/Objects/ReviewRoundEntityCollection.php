<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class ReviewRoundEntityCollection extends ValueObjectCollection
{
	protected function __construct(ReviewRoundEntity ...$reviewRoundEntities) {}
}
