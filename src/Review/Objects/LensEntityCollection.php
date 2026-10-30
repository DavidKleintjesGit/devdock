<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class LensEntityCollection extends ValueObjectCollection
{
	protected function __construct(LensEntity ...$lensEntities) {}
}
