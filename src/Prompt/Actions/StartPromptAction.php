<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Access\Enums\GitGrantEnum;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunObject;
use Src\Review\Actions\WriteAgentContextAction;
use Src\Task\Actions\OpenAgentAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Objects\TaskAgentEntity;
use Src\Worktree\Actions\EnsureOnBranchAction;
use Src\Worktree\Actions\EnsureReadyAction;
use Src\Worktree\Actions\ResolveWorkingCopyAction;
use Src\Worktree\Enums\WorkKindEnum;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * Sets a prompt ready and has it carried out outside the request. The progress
 * runs through the row the command updates.
 *
 * A task is worked in one agent. Has it none yet, one is opened here under a
 * key of our own before anything leaves, so the session is ours from the first
 * round instead of something we recognise afterwards.
 *
 * A turn only starts in a working copy that passes its checklist and has no
 * other work running in it. A refusal is written as a failed turn, so the
 * chat says why nothing happened.
 *
 * @method static PromptRunEntity execute(PromptRunObject $promptRunObject)
 */
class StartPromptAction extends Command
{
	public function __construct(
		public readonly PromptRunObject $promptRunObject,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
		TaskRepositoryContract $taskRepository,
	): PromptRunEntity {
		try {
			$directory = $this->directory();
			// a turn that finishes a rebase DevDock began may start while that rebase stands open
			EnsureReadyAction::execute($directory, $this->promptRunObject->gitGrant === GitGrantEnum::REBASE ? WorkKindEnum::CONFLICT_TURN : WorkKindEnum::AGENT_TURN);
		} catch (WorktreeNotPossibleException|SetupIncompleteException|WorkingCopyBusyException $failure) {
			$promptRunEntity = $promptRunRepository->create($this->promptRunObject);

			return $promptRunRepository->markFailed($promptRunEntity->promptRunId, $failure->getMessage());
		}

		// the way of working lies in the working copy itself, so the agent reads
		// it without a line of it ending up in the prompt
		WriteAgentContextAction::execute($this->promptRunObject->card, $directory);

		$taskAgentEntity = $this->agentFor($taskRepository, $directory);

		$promptRunEntity = $promptRunRepository->create(new PromptRunObject(
			card: $this->promptRunObject->card,
			reviewRoundId: $this->promptRunObject->reviewRoundId,
			project: $this->promptRunObject->project,
			branch: $this->promptRunObject->branch,
			directory: $directory,
			ticketKey: $this->promptRunObject->ticketKey,
			sessionUuid: $taskAgentEntity->sessionUuid,
			claimsSession: ! $taskAgentEntity->hasStarted(),
			prompt: $this->promptRunObject->prompt,
			gitGrant: $this->promptRunObject->gitGrant,
		));

		$claudeProcessRepository->startDetached($promptRunEntity->promptRunId);

		return $promptRunEntity;
	}

	/**
	 * The agent this round lands in: the one the task already works with, and
	 * otherwise a fresh key handed out here.
	 */
	private function agentFor(TaskRepositoryContract $taskRepository, ?string $directory): TaskAgentEntity
	{
		$taskAgentEntity = $taskRepository->getByKey($this->promptRunObject->card)?->activeAgent();

		return $taskAgentEntity ?? OpenAgentAction::execute($this->promptRunObject->card, $directory);
	}

	/**
	 * @throws WorktreeNotPossibleException
	 */
	private function directory(): ?string
	{
		$project = $this->promptRunObject->project;
		$branch = $this->promptRunObject->branch;

		if ($project === null) {
			return null;
		}

		// a task without a branch has no working copy, and the gate refuses it
		if ($branch === null) {
			return null;
		}

		$directory = ResolveWorkingCopyAction::execute($project, $branch, $this->promptRunObject->card);

		if ($directory !== null) {
			EnsureOnBranchAction::execute($directory, $branch, acceptsRebase: $this->promptRunObject->gitGrant === GitGrantEnum::REBASE);
		}

		return $directory;
	}
}
