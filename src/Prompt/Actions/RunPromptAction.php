<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Access\Actions\GetAccessPolicyAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Enums\ReportStatusEnum;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;
use Src\Review\Actions\StartRoundAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Exceptions\RoundNotStartedException;
use Src\Session\Actions\RenameSessionAction;
use Src\Session\Actions\ScanSessionsAction;
use Src\Session\Objects\SessionEntity;
use Src\Task\Actions\GetReadableDirectoriesAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
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
				GetAccessPolicyAction::execute(AgentRoleEnum::AGENT),
				$promptRunEntity,
				GetReadableDirectoriesAction::execute($promptRunEntity->card),
				$promptRunRepository->logPath($this->promptRunId),
				$promptRunRepository->writeInstructions($this->promptRunId, implode("\n\n", array_filter([
					DescribeChatAction::execute(),
					$promptRunEntity->directory === null ? null : resolve(AgentContextRepositoryContract::class)->getAgentContext($promptRunEntity->directory),
				]))),
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

		// a turn on a round sends the lenses back over the work, but only when the
		// agent reports it done; a question or open points wait for you
		if ($promptRunEntity->reviewRoundId !== null && ReadReportAction::execute($promptRunEntity->output)?->status === ReportStatusEnum::DONE) {
			try {
				StartRoundAction::execute($promptRunEntity->card, $promptRunEntity->directory);
			} catch (WorktreeNotPossibleException|RoundNotStartedException|SetupIncompleteException|WorkingCopyBusyException) {
				// the folder left the branch of the task, or the loop is full; it waits until you start the next round yourself
			}
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
