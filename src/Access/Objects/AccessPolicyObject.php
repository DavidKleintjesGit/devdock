<?php

declare(strict_types=1);

namespace Src\Access\Objects;

use Src\Access\Enums\AgentRoleEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * What one role may reach when DevDock starts Claude Code for it: the tools it
 * is denied, the Atlassian operations it may still write through, and the git
 * commands it may run.
 */
class AccessPolicyObject extends ValueObject
{
	/**
	 * @param  list<string>  $deniedTools
	 * @param  list<string>  $writableOperations
	 * @param  list<string>  $gitReading
	 */
	public function __construct(
		public readonly AgentRoleEnum $agentRole,
		public readonly array $deniedTools,
		public readonly array $writableOperations,
		public readonly array $gitReading,
	) {
		parent::__construct();
	}

	/**
	 * Whether a write to Atlassian is put to the write guard instead of being denied outright.
	 */
	public function guardsAtlassianWrites(): bool
	{
		return $this->writableOperations !== [];
	}
}
