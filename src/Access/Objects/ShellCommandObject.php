<?php

declare(strict_types=1);

namespace Src\Access\Objects;

use Src\Access\Enums\GitGrantEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * A shell command an agent, lens or helper is about to run, as Claude Code
 * hands it to the guard.
 */
class ShellCommandObject extends ValueObject
{
	/**
	 * @param  list<GitGrantEnum>  $grants  git work you handed over for this turn
	 */
	public function __construct(
		public readonly string $command,
		public readonly array $grants = [],
	) {
		parent::__construct();
	}
}
