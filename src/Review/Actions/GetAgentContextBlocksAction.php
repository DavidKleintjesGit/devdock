<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;

/**
 * The texts the context is built from, so they can be read and changed.
 *
 * @method static array execute()
 */
class GetAgentContextBlocksAction extends Command
{
	/**
	 * @return array<string, string>
	 */
	public function handle(AgentContextRepositoryContract $agentContextRepository): array
	{
		return $agentContextRepository->getBlocks();
	}
}
