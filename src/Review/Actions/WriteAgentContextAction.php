<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;

/**
 * Lays the way of working down in the working copy itself, where every turn
 * of the agent picks it up next to its prompt.
 *
 * It is written again at every round, so it always says where the task stands.
 *
 * @method static bool execute(string $card, ?string $worktreePath)
 */
class WriteAgentContextAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $worktreePath,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): bool
	{
		if ($this->worktreePath === null) {
			return false;
		}

		$contents = ComposeAgentContextAction::execute($this->card, $this->worktreePath);

		if ($contents === null) {
			return false;
		}

		$written = $agentContextRepository->write($this->worktreePath, $contents);

		if ($written) {
			$agentContextRepository->exclude($this->worktreePath);
		}

		return $written;
	}
}
