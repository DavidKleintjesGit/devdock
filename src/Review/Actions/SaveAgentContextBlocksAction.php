<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;

/**
 * Writes the way of working down. It holds for every project; the facts of a
 * task are filled in when the context is laid down in a working copy.
 *
 * @method static void execute(array $blocks)
 */
class SaveAgentContextBlocksAction extends Command
{
	/**
	 * @param  array<string, string>  $blocks
	 */
	public function __construct(
		public readonly array $blocks,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): void
	{
		$agentContextRepository->saveBlocks($this->blocks);
	}
}
