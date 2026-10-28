<?php

declare(strict_types=1);

namespace Src\Packages\ValueObject\Contracts;

/**
 * This interface guarantees that our Value Object has a way to be cast to a string
 */
interface StringableValueObject
{
	public function toString(): string;
}
