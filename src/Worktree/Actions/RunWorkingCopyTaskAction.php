<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Prompt\Actions\GenerateBranchNameAction;
use Src\Prompt\Actions\GenerateCommitMessageAction;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\RebaseOutcomeEnum;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;
use Src\Worktree\Objects\WorkingCopyTaskStateObject;
use Throwable;

/**
 * Carries out a task on the working copy of a card, in the process that was
 * started for it, and writes down how it ended.
 *
 * @method static WorkingCopyTaskStateObject execute(WorkingCopyTaskObject $workingCopyTaskObject)
 */
class RunWorkingCopyTaskAction extends Command
{
	public function __construct(
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {}

	public function handle(): WorkingCopyTaskStateObject
	{
		$key = StartWorkingCopyTaskAction::keyFor($this->workingCopyTaskObject->card);
		$startedAt = (array) Cache::get($key, []);

		try {
			$ending = match ($this->workingCopyTaskObject->task) {
				WorkingCopyTaskEnum::LINK => ['failure' => LinkWorkingCopyAction::execute(
					$this->workingCopyTaskObject->card,
					$this->workingCopyTaskObject->project,
					$this->workingCopyTaskObject->branch,
					$this->workingCopyTaskObject->fill,
					$this->workingCopyTaskObject->carriesChanges,
				)],
				WorkingCopyTaskEnum::REBASE => $this->rebaseEnding(),
				WorkingCopyTaskEnum::RESOLVE_CONFLICTS => $this->resolveConflictsEnding(),
				WorkingCopyTaskEnum::PUSH => $this->pushEnding(),
				WorkingCopyTaskEnum::OWN_TEST_DATABASE => $this->ownTestDatabaseEnding(),
				WorkingCopyTaskEnum::COMMIT_MESSAGE => $this->commitMessageEnding(),
				WorkingCopyTaskEnum::BRANCH_NAME => $this->branchNameEnding(),
			};
		} catch (Throwable $exception) {
			report($exception);
			$ending = ['failure' => $exception->getMessage()];
		}

		$data = [
			'task' => $this->workingCopyTaskObject->task->value,
			'is_running' => false,
			'started_at' => $startedAt['started_at'] ?? CarbonImmutable::now()->toIso8601String(),
			'failure' => $ending['failure'] ?? null,
			'notice' => $ending['notice'] ?? null,
			'result' => $ending['result'] ?? [],
		];

		Cache::forever($key, $data);

		return WorkingCopyTaskStateObject::from($data);
	}

	/**
	 * @return array{failure?: ?string, notice?: string}
	 */
	private function rebaseEnding(): array
	{
		$failure = RebaseWorktreeAction::execute((string) $this->workingCopyTaskObject->path);

		$this->refreshStanding();

		return $failure === null
			? ['notice' => __('Gerebased op :main.', ['main' => $this->targetBranch()])]
			: ['failure' => $failure];
	}

	/**
	 * @return array{failure?: ?string, notice?: string, result?: array<string, string>}
	 */
	private function resolveConflictsEnding(): array
	{
		$rebaseOutcomeObject = ResolveConflictsAction::execute(
			$this->workingCopyTaskObject->card,
			$this->workingCopyTaskObject->project,
			$this->workingCopyTaskObject->branch,
			$this->workingCopyTaskObject->ticketKey,
			(string) $this->workingCopyTaskObject->path,
		);

		$this->refreshStanding();

		return match ($rebaseOutcomeObject->outcome) {
			RebaseOutcomeEnum::CONFLICTED => ['result' => ['tab' => 'Agent']],
			RebaseOutcomeEnum::DONE => ['notice' => __('Gerebased op :main zonder conflicten.', ['main' => $rebaseOutcomeObject->targetBranch])],
			RebaseOutcomeEnum::FAILED => ['failure' => $rebaseOutcomeObject->failure],
		};
	}

	/**
	 * @return array{failure?: string, notice?: string}
	 */
	private function pushEnding(): array
	{
		if (PushBranchAction::execute((string) $this->workingCopyTaskObject->path)) {
			return ['notice' => __('Gepusht naar origin.')];
		}

		$worktreeEntity = resolve(WorktreeRepositoryContract::class)->getFor($this->workingCopyTaskObject->project, $this->workingCopyTaskObject->branch);

		return ['failure' => ($worktreeEntity?->onlyOnUpstream ?? 0) > 0
			? __('Overschrijven lukte niet: origin veranderde sinds DevDock er voor het laatst keek. Haal eerst op en kijk wat er bij kwam.')
			: __('Pushen lukte niet. Een taakbranch pusht nooit naar de hoofdlijn.')];
	}

	/**
	 * @return array{failure?: ?string, notice?: string}
	 */
	private function ownTestDatabaseEnding(): array
	{
		$failure = resolve(WorktreeDatabaseRepositoryContract::class)->giveOwnTestDatabase(
			(string) $this->projectEntity()?->path,
			(string) $this->workingCopyTaskObject->path,
			$this->workingCopyTaskObject->branch,
		);

		return $failure === null
			? ['notice' => __('Deze kopie test nu in een eigen database.')]
			: ['failure' => $failure];
	}

	/**
	 * @return array{notice?: string, result?: array<string, string>}
	 */
	private function commitMessageEnding(): array
	{
		$commitMessageObject = GenerateCommitMessageAction::execute(
			(string) $this->workingCopyTaskObject->path,
			$this->workingCopyTaskObject->files,
			$this->workingCopyTaskObject->ticketKey,
		);

		return $commitMessageObject === null
			? ['notice' => __('Er kwam geen bericht terug. Probeer het nog eens of schrijf het zelf.')]
			: ['result' => ['title' => $commitMessageObject->title, 'body' => $commitMessageObject->body]];
	}

	/**
	 * @return array{failure?: string, result?: array<string, string>}
	 */
	private function branchNameEnding(): array
	{
		$branch = GenerateBranchNameAction::execute($this->workingCopyTaskObject->card);

		return $branch === null
			? ['failure' => __('De agent kon geen branchnaam voorstellen.')]
			: ['result' => ['branch' => $branch]];
	}

	private function refreshStanding(): void
	{
		$projectPath = $this->projectEntity()?->path;

		if ($projectPath !== null) {
			resolve(BranchStandingRepositoryContract::class)->warm($projectPath, [$this->workingCopyTaskObject->branch], again: true);
		}
	}

	private function targetBranch(): string
	{
		return (string) resolve(WorktreeRepositoryContract::class)->getFor($this->workingCopyTaskObject->project, $this->workingCopyTaskObject->branch)?->targetBranch;
	}

	private function projectEntity(): ?ProjectEntity
	{
		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $this->workingCopyTaskObject->project);
	}
}
