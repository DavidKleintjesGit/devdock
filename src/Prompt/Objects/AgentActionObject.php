<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Enums\AgentActionEnum;

/**
 * One action an agent offered at the end of its answer, with what it is about.
 */
class AgentActionObject extends ValueObject
{
	public function __construct(
		public readonly AgentActionEnum $action,
		public readonly string $argument,
	) {
		parent::__construct();
	}

	public function label(): string
	{
		return $this->action->label($this->argument);
	}
}
