<?php

declare(strict_types=1);

namespace Src\Packages\Cqrs\Traits;

use Illuminate\Foundation\Bus\Dispatchable;

trait Executable
{
	use Dispatchable;

	public static function execute(mixed ...$arguments): mixed
	{
		return self::dispatchSync(...$arguments);
	}
}
