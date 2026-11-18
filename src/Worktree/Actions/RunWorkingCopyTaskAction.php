<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Src\Board\Actions\ArchiveCardAction;
use Src\Board\Actions\CleanUpCardAction;
use Src\Board\Actions\DeleteCardAction;
use Src\Board\Objects\TodoId;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Prompt\Actions\GenerateBranchNameAction;
use Src\Prompt\Actions\GenerateCommitMessageAction;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Objects\PromptRunObject;
use Src\Review\Actions\StartRoundAction;
use Src\Review\Exceptions\RoundNotStartedException;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
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
				WorkingCopyTaskEnum::COMMIT => $this->commitEnding(),
				WorkingCopyTaskEnum::PROMPT => $this->promptEnding(),
				WorkingCopyTaskEnum::ROUND => $this->roundEnding(),
				WorkingCopyTaskEnum::RETARGET => $this->retargetEnding(),
				WorkingCopyTaskEnum::CLEAN_UP => $this->cardEnding(CleanUpCardAction::execute($this->workingCopyTaskObject->card, $this->workingCopyTaskObject->discardsWork), __('Opgeruimd.')),
				WorkingCopyTaskEnum::ARCHIVE => $this->cardEnding(ArchiveCardAction::execute($this->workingCopyTaskObject->card, $this->workingCopyTaskObject->discardsWork), __('Gearchiveerd.')),
				WorkingCopyTaskEnum::DELETE => $this->cardEnding($this->deleteCard(), __('Verwijderd.')),
			};
		} catch (Throwable $exception) {
			report($exception);
			$ending = ['failure' => $exception->getMessage()];
		}

		// the ending is written down and the next task in line takes over in one step, so a task that arrives meanwhile is never lost
		return Cache::lock(StartWorkingCopyTaskAction::lockFor($this->workingCopyTaskObject->card), 10)->block(5, function () use ($key, $startedAt, $ending): WorkingCopyTaskStateObject {
			$kept = (array) Cache::get($key, []);
			$endings = [...(array) ($kept['endings'] ?? []), [
				// a moment in milliseconds names the ending, so a panel can say up to where it has seen them
				'at' => (int) round(microtime(true) * 1000),
				'task' => $this->workingCopyTaskObject->task->value,
				'failure' => $ending['failure'] ?? null,
				'notice' => $ending['notice'] ?? null,
				'result' => $ending['result'] ?? [],
			]];
			$queued = array_values((array) ($kept['queued'] ?? []));
			$next = array_shift($queued);

			$data = $next === null ? [
				'task' => $this->workingCopyTaskObject->task->value,
				'is_running' => false,
				'started_at' => $startedAt['started_at'] ?? CarbonImmutable::now()->toIso8601String(),
				'failure' => $ending['failure'] ?? null,
				'notice' => $ending['notice'] ?? null,
				'result' => $ending['result'] ?? [],
				'queued' => [],
				'endings' => $endings,
			] : [
				'task' => WorkingCopyTaskObject::fromArgument($next)->task->value,
				'current' => $next,
				'is_running' => true,
				'started_at' => CarbonImmutable::now()->toIso8601String(),
				'failure' => null,
				'next' => $next,
				'queued' => $queued,
				'endings' => $endings,
			];

			Cache::forever($key, $data);

			return WorkingCopyTaskStateObject::from($data);
		});
	}

	/**
	 * @return array{failure?: string, notice?: string}
	 */
	private function commitEnding(): array
	{
		$files = $this->workingCopyTaskObject->files;
		$failure = CommitChangesAction::execute((string) $this->workingCopyTaskObject->path, (string) $this->workingCopyTaskObject->message, (string) $this->workingCopyTaskObject->body, $files);

		if ($failure !== null) {
			return ['failure' => $failure];
		}

		$notice = trans_choice('Vastgelegd: :count bestand.|Vastgelegd: :count bestanden.', count($files), ['count' => count($files)]);

		if (! $this->workingCopyTaskObject->pushesAfter) {
			return ['notice' => $notice];
		}

		$pushEnding = $this->pushEnding();

		return isset($pushEnding['failure'])
			? ['failure' => $notice . ' ' . $pushEnding['failure']]
			: ['notice' => __('Vastgelegd en gepusht naar origin.')];
	}

	/**
	 * A turn of the agent that DevDock could not start writes its reason on the turn itself, in the chat.
	 *
	 * @return array<string, never>
	 */
	private function promptEnding(): array
	{
		StartPromptAction::execute(PromptRunObject::from([
			'card' => $this->workingCopyTaskObject->card,
			'project' => $this->workingCopyTaskObject->project,
			'branch' => $this->workingCopyTaskObject->branch,
			'ticket_key' => $this->workingCopyTaskObject->ticketKey,
			'prompt' => (string) $this->workingCopyTaskObject->prompt,
			// a fix you picked from a round belongs to it, so the lenses go again once it is done
			'review_round_id' => $this->workingCopyTaskObject->reviewRoundId?->id,
		]));

		return [];
	}

	/**
	 * @return array{failure?: string}
	 */
	private function roundEnding(): array
	{
		try {
			$reviewRoundEntity = StartRoundAction::execute($this->workingCopyTaskObject->card, (string) $this->workingCopyTaskObject->path, $this->workingCopyTaskObject->restartsLoop);
		} catch (WorktreeNotPossibleException|RoundNotStartedException|SetupIncompleteException|WorkingCopyBusyException $refusal) {
			return ['failure' => $refusal->getMessage()];
		}

		return $reviewRoundEntity === null ? ['failure' => __('Er ging geen ronde open.')] : [];
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
	 * @return array{failure?: ?string, notice?: string, result?: array<string, string>}
	 */
	private function retargetEnding(): array
	{
		$rebaseOutcomeObject = RetargetWorkingCopyAction::execute(
			$this->workingCopyTaskObject->card,
			$this->workingCopyTaskObject->project,
			$this->workingCopyTaskObject->branch,
			$this->workingCopyTaskObject->ticketKey,
			(string) $this->workingCopyTaskObject->path,
			(string) $this->workingCopyTaskObject->target,
		);

		$this->refreshStanding();

		return match ($rebaseOutcomeObject->outcome) {
			RebaseOutcomeEnum::CONFLICTED => ['result' => ['tab' => 'Agent']],
			RebaseOutcomeEnum::DONE => ['notice' => __('Op :target gezet en gerebased. Push om origin bij te werken.', ['target' => $rebaseOutcomeObject->targetBranch])],
			RebaseOutcomeEnum::FAILED => ['failure' => $rebaseOutcomeObject->failure],
		};
	}

	/**
	 * @return array{failure?: string, notice?: string}
	 */
	private function cardEnding(?string $failure, string $notice): array
	{
		return $failure === null ? ['notice' => $notice] : ['failure' => $failure];
	}

	private function deleteCard(): ?string
	{
		$todoId = TodoId::fromBoardKey($this->workingCopyTaskObject->card);

		return $todoId === null
			? __('Alleen een eigen to-do kan verwijderd worden; archiveer de rest.')
			: DeleteCardAction::execute($todoId, $this->workingCopyTaskObject->discardsWork);
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
		$worktreeDatabaseRepository = resolve(WorktreeDatabaseRepositoryContract::class);
		$projectPath = (string) $this->projectEntity()?->path;

		$failure = $worktreeDatabaseRepository->giveOwnTestDatabase($projectPath, (string) $this->workingCopyTaskObject->path, $this->workingCopyTaskObject->branch)
			?? $worktreeDatabaseRepository->giveOwnConnections($projectPath, (string) $this->workingCopyTaskObject->path, $this->workingCopyTaskObject->branch, true);

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
