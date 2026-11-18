<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\CardArchiveRepositoryContract;
use Src\Packages\Cqrs\Command;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Puts a cleaned-up or archived task back to work: the card returns to the
 * board, its last agent takes over again, and a new working copy is made
 * from its branch on origin in a process of its own. A task that never had
 * a branch only returns to the board.
 *
 * @method static void execute(string $card)
 */
class ReopenCardAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(
		CardArchiveRepositoryContract $cardArchiveRepository,
		TaskRepositoryContract $taskRepository,
	): void {
		$cardArchiveEntity = $cardArchiveRepository->getFor($this->card);
		$cardArchiveRepository->forget($this->card);

		if ($cardArchiveEntity?->project === null || $cardArchiveEntity->branch === null) {
			return;
		}

		if ($cardArchiveEntity->agentSessionUuid !== null) {
			$taskRepository->activateAgent($taskRepository->firstOrCreate($this->card)->taskId, $cardArchiveEntity->agentSessionUuid);
		}

		StartWorkingCopyTaskAction::execute(new WorkingCopyTaskObject(
			card: $this->card,
			task: WorkingCopyTaskEnum::LINK,
			project: $cardArchiveEntity->project,
			branch: $cardArchiveEntity->branch,
		));
	}
}
