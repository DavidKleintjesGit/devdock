<?php

declare(strict_types=1);

namespace Src\Packages\Cqrs;

use Src\Packages\Cqrs\Traits\Executable;

abstract class Command implements \Src\Packages\Cqrs\Contracts\Command
{
	use Executable;
}
