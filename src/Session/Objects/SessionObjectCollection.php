<?php

declare(strict_types=1);

namespace Src\Session\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class SessionObjectCollection extends ValueObjectCollection
{
	protected function __construct(SessionObject ...$sessionObjects)
	{
	}
}
