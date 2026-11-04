<?php

declare(strict_types=1);

namespace Src\Access\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A shell command an agent, lens or helper is about to run, as Claude Code
 * hands it to the guard.
 */
class ShellCommandObject extends ValueObject
{
	public function __construct(
		public readonly string $command,
	) {
		parent::__construct();
	}
}
