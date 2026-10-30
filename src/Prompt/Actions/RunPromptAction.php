<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;
use Src\Review\Actions\StartRoundAction;
use Src\Session\Actions\RenameSessionAction;
use Src\Session\Actions\ScanSessionsAction;
use Src\Session\Objects\SessionEntity;
use Src\Task\Actions\GetReadableDirectoriesAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Throwable;

/**
 * Has Claude Code carry out one prompt and writes the answer back to the
 * board.
 *
 * @method static PromptRunEntity execute(PromptRunId $promptRunId)
 */
class RunPromptAction extends Command
{
	public function __construct(
		public readonly PromptRunId $promptRunId,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
		TaskRepositoryContract $taskRepository,
	): PromptRunEntity {
		$promptRunEntity = $promptRunRepository->getById($this->promptRunId);

		if ($promptRunEntity === null) {
			abort(404);
		}

		try {
			$promptResultObject = $claudeProcessRepository->run(
				$promptRunEntity,
				GetReadableDirectoriesAction::execute($promptRunEntity->card),
			);
		} catch (Throwable $failure) {
			return $promptRunRepository->markFailed($this->promptRunId, $failure->getMessage());
		}

		$promptRunEntity = $promptRunRepository->markFinished($this->promptRunId, $promptResultObject);

		// the agent has been changing files, so what the board remembers about
		// this working copy is out of date
		if ($promptRunEntity->directory !== null) {
			resolve(WorktreeRepositoryContract::class)->forgetStatus($promptRunEntity->directory);
		}

		// deze beurt hoorde bij een ronde: de punten zijn bijgewerkt, dus de
		// lenzen mogen er opnieuw over
		if ($promptRunEntity->reviewRoundId !== null) {
			StartRoundAction::execute($promptRunEntity->card, $promptRunEntity->directory);
		}

		// the key is taken now, so the next round resumes it instead of claiming it
		if (filled($promptRunEntity->sessionUuid)) {
			$taskRepository->markAgentStarted($promptRunEntity->sessionUuid);
		}

		$this->nameAfterTicket($promptRunEntity);

		return $promptRunEntity;
	}

	/**
	 * A fresh session gets the ticket number up front, so it lands with the
	 * right task on the board.
	 */
	private function nameAfterTicket(PromptRunEntity $promptRunEntity): void
	{
		$sessionEntities = ScanSessionsAction::execute();

		if (blank($promptRunEntity->ticketKey) || blank($promptRunEntity->sessionUuid)) {
			return;
		}

		$sessionEntity = $sessionEntities
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->uuid === $promptRunEntity->sessionUuid)
			->first();

		if ($sessionEntity === null || str_contains((string) $sessionEntity->title, (string) $promptRunEntity->ticketKey)) {
			return;
		}

		RenameSessionAction::execute(
			$sessionEntity->sessionId,
			$promptRunEntity->ticketKey . ' · ' . $sessionEntity->name(),
		);
	}
}
