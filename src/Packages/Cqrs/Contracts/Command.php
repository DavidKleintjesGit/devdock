<?php

declare(strict_types=1);

namespace Src\Packages\Cqrs\Contracts;

interface Command
{
	public static function execute(): mixed;
}
