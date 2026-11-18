<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\CardArchiveRepositoryContract;
use Src\Board\Enums\CleanUpEnum;
use Src\Board\Objects\CleanUpFactsObject;
use Src\Board\Policies\CleanUpPolicy;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\StopPromptAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Actions\RemoveWorktreeAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Clears away the local work of a task: an agent that still runs stops, the
 * working copy goes with its databases, site and local branch, and the task
 * lets go of its branch and agent. The card stays, with its conversation and
 * rounds for reading back, and remembers where the work stood so it can be
 * opened again. Returns why it did not happen, or null.
 *
 * @method static ?string execute(string $card, bool $discardsWork = false)
 */
class CleanUpCardAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly bool $discardsWork = false,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		TaskRepositoryContract $taskRepository,
		CardArchiveRepositoryContract $cardArchiveRepository,
	): ?string {
		$worktreeEntity = GetWorktreesAction::execute()->first(fn (WorktreeEntity $worktreeEntity): bool => $worktreeEntity->card === $this->card);
		$taskEntity = $taskRepository->getByKey($this->card);
		$uncommittedFiles = $worktreeEntity?->changedFiles ?? 0;
		$unpushedCommits = $worktreeEntity === null ? 0 : count($worktreeRepository->getUnpushedCommits($worktreeEntity->path));

		$cleanUp = resolve(CleanUpPolicy::class)->decide(new CleanUpFactsObject(
			uncommittedFiles: $uncommittedFiles,
			unpushedCommits: $unpushedCommits,
			discardsWork: $this->discardsWork,
		));

		if ($cleanUp === CleanUpEnum::WORK_AT_STAKE) {
			return __('Niet opgeruimd: :work.', ['work' => CleanUpEnum::workAtStake($uncommittedFiles, $unpushedCommits)]);
		}

		StopPromptAction::execute($this->card);

		$branch = $worktreeEntity?->branch ?? $taskEntity?->branch();

		if ($worktreeEntity !== null && ! RemoveWorktreeAction::execute($worktreeEntity->worktreeId, $this->discardsWork)) {
			return __('Git weigerde de werkkopie :path weg te halen.', ['path' => $worktreeEntity->path]);
		}

		if ($taskEntity !== null) {
			if ($taskEntity->branch() !== null) {
				$taskRepository->unlink($taskEntity->taskId, TaskLinkKindEnum::BRANCH, $taskEntity->branch());
			}

			$taskRepository->retireAgents($taskEntity->taskId);
		}

		$cardArchiveRepository->markCleanedUp(
			$this->card,
			$worktreeEntity?->project ?? $taskEntity?->project(),
			$branch,
			$taskEntity?->activeAgent()?->sessionUuid,
		);

		return null;
	}
}
