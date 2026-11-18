<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\CardArchiveRepositoryContract;
use Src\Board\Objects\TodoId;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\AttachmentRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Task\Actions\DeleteTaskAction;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Takes a to-do away altogether: its local work is cleaned up first, then
 * its conversation, rounds, reports, attachments and sessions go, and the
 * to-do itself. Returns why it did not happen, or null.
 *
 * @method static ?string execute(TodoId $todoId, bool $discardsWork = false)
 */
class DeleteCardAction extends Command
{
	public function __construct(
		public readonly TodoId $todoId,
		public readonly bool $discardsWork = false,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ReviewRoundRepositoryContract $reviewRoundRepository,
		AttachmentRepositoryContract $attachmentRepository,
		AgentContextRepositoryContract $agentContextRepository,
		SessionFileRepositoryContract $sessionFileRepository,
		CardArchiveRepositoryContract $cardArchiveRepository,
	): ?string {
		$card = 'todo:' . $this->todoId->id;
		// the reports and sessions are found by the folder, which the clean-up takes away
		$worktreeEntity = GetWorktreesAction::execute()->first(fn (WorktreeEntity $worktreeEntity): bool => $worktreeEntity->card === $card);
		$failure = CleanUpCardAction::execute($card, $this->discardsWork);

		if ($failure !== null) {
			return $failure;
		}

		if ($worktreeEntity !== null) {
			$agentContextRepository->removeReports($worktreeEntity->path, $card);

			if ($worktreeEntity->origin->isOurs()) {
				$sessionFileRepository->deleteFor($worktreeEntity->path);
			}
		}

		$promptRunRepository->deleteForCard($card);
		$reviewRoundRepository->deleteLogsForCard($card);
		$reviewRoundRepository->deleteForCard($card);
		$attachmentRepository->deleteFor($card);
		DeleteTaskAction::execute($card);
		DeleteTodoAction::execute($this->todoId);
		$cardArchiveRepository->forget($card);

		return null;
	}
}
