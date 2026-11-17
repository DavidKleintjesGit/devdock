<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;
use Src\Board\Objects\CardObject;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\GetStandingsAction;
use Src\Library\Enums\StandingEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\StandingObject;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Actions\CarryOverAgainAction;
use Src\Worktree\Actions\CreateWorktreeDatabaseAction;
use Src\Worktree\Actions\DescribeCleanUpAction;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Actions\ForgetWorkingCopyTaskAction;
use Src\Worktree\Actions\GetWorkingCopyTaskAction;
use Src\Worktree\Actions\GetBranchChainAction;
use Src\Worktree\Actions\SetUpstreamAction;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Actions\TakeWorkingCopyTaskEndingsAction;
use Src\Worktree\Actions\UseWorktreeDatabaseAction;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\DiffViewEnum;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\BranchChainObject;
use Src\Worktree\Objects\BranchLinkObject;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\DatabaseObject;
use Src\Worktree\Objects\WorkingCopyTaskObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The Werkkopie tab of a card: bringing the branch up to date, picking what
 * goes into a commit, pushing, and the database and upstream of the copy.
 */
trait WithWorkingCopy
{
	use WithCodeReader;

	/** @var array<int, string> the changed files that stay out of the next commit */
	public array $skippedFiles = [];

	public bool $pushAfterCommit = false;

	public bool $showAllChanges = false;


	/**
	 * How many files of the branch show before "Nog n bestanden".
	 */
	private const CHANGES_SHOWN = 4;

	public ?string $databaseDialog = null;

	public string $databaseName = '';

	public string $databaseFill = 'copy';

	public bool $upstreamDialog = false;

	public string $upstreamChoice = '';

	/** The moment, in milliseconds, of the last task ending this page received. */
	public int $seenEndingsUntil = 0;

	public bool $targetDialog = false;

	public string $targetChoice = '';

	/** The turn of the agent whose changes the round view of the code reader shows. */
	public ?int $diffRun = null;

	public function rebaseOnMain(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		// the button is grey then; a request that still comes in is refused the same way
		if ($worktreeEntity->changedFiles > 0) {
			$this->failure = __('Er staat werk dat niet gecommit is. Commit eerst, dan kan de rebase.');

			return;
		}

		$blockingLink = $this->branchChainFor($worktreeEntity)?->blockingLink();

		if ($blockingLink !== null) {
			$this->failure = $this->levelFirst($blockingLink);

			return;
		}

		$this->startWorkingCopyTask(WorkingCopyTaskEnum::REBASE, $worktreeEntity);
	}

	/**
	 * Breaks off the rebase that stands open, so the branch is as it was before.
	 */
	public function abortRebase(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$isAborted = resolve(WorktreeRepositoryContract::class)->abortRebase($worktreeEntity->path);

		$this->failure = $isAborted ? null : __('De rebase kon niet afgebroken worden. Doe het zelf met git rebase --abort.');
		$this->committed = $isAborted ? __('Rebase afgebroken; de branch staat weer zoals hij was.') : null;

		$this->refreshStanding($worktreeEntity);
	}

	/**
	 * Gives an existing working copy a test database of its own, so its tests
	 * stop sharing one with the project and the other copies.
	 */
	public function giveOwnTestDatabase(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$this->startWorkingCopyTask(WorkingCopyTaskEnum::OWN_TEST_DATABASE, $worktreeEntity);
	}

	/**
	 * Begins the rebase on the branch the work merges into and, when it stops
	 * on conflicts, gives the agent one turn in which it may finish it.
	 */
	public function resolveConflictsWithAgent(): void
	{
		$this->failure = null;
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$worktreeEntity = $this->openWorktree();

		if ($cardObject === null || $worktreeEntity === null || $cardObject->project === null || $cardObject->branch === null) {
			return;
		}

		$blockingLink = $worktreeEntity->isRebasing ? null : $this->branchChainFor($worktreeEntity)?->blockingLink();

		if ($blockingLink !== null) {
			$this->failure = $this->levelFirst($blockingLink);

			return;
		}

		$this->startWorkingCopyTask(WorkingCopyTaskEnum::RESOLVE_CONFLICTS, $worktreeEntity, ticketKey: $cardObject->ticketKey);
	}

	public function toggleCommitFile(string $path): void
	{
		$this->skippedFiles = in_array($path, $this->skippedFiles, true)
			? array_values(array_diff($this->skippedFiles, [$path]))
			: [...$this->skippedFiles, $path];
	}

	/**
	 * Writes the ticked files down as one commit, and pushes right after when
	 * that is switched on. Both happen on your press and nowhere else.
	 */
	public function commitChanges(): void
	{
		$this->failure = null;

		$this->validate(['commitMessage' => ['required', 'min:5', 'max:200']], [
			'commitMessage.required' => __('Geef de commit een titel.'),
			'commitMessage.min' => __('Geef de commit een titel.'),
		]);

		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			$this->failure = __('Er is geen werkkopie om in te committen.');

			return;
		}

		$files = $this->filesToCommit($worktreeEntity);

		if ($files === []) {
			$this->failure = __('Er is niets aangevinkt om te committen.');

			return;
		}

		$this->failure = $this->startLinking(new WorkingCopyTaskObject(
			card: (string) $this->openCard,
			task: WorkingCopyTaskEnum::COMMIT,
			project: $worktreeEntity->project,
			branch: $worktreeEntity->branch,
			path: $worktreeEntity->path,
			files: $files,
			message: $this->commitMessage,
			body: $this->commitBody,
			pushesAfter: $this->pushAfterCommit,
		));
		$this->committed = null;
		$this->commitMessage = '';
		$this->commitBody = '';
		$this->skippedFiles = [];
		// pushing along is chosen per commit; the switch is off again for the next one
		$this->pushAfterCommit = false;
	}

	public function pushBranch(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$this->startWorkingCopyTask(WorkingCopyTaskEnum::PUSH, $worktreeEntity);
	}

	public function startDatabase(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

		$this->databaseName = $this->describeDatabase($worktreeEntity)?->suggestionFor($cardObject?->ticketKey ?? $worktreeEntity->branch) ?? '';
		$this->databaseFill = DatabaseFillEnum::COPY->value;
		$this->databaseDialog = 'new';
	}

	public function startChooseDatabase(): void
	{
		$this->databaseDialog = 'choose';
	}

	public function closeDatabase(): void
	{
		$this->databaseDialog = null;
	}

	public function createDatabase(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$failure = CreateWorktreeDatabaseAction::execute(
			$worktreeEntity,
			trim($this->databaseName),
			DatabaseFillEnum::tryFrom($this->databaseFill) ?? DatabaseFillEnum::COPY,
		);

		$this->failure = $failure;

		if ($failure === null) {
			$this->databaseDialog = null;
			$this->committed = __('Database :name staat klaar.', ['name' => trim($this->databaseName)]);
		}
	}

	public function useDatabase(string $name): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity !== null) {
			UseWorktreeDatabaseAction::execute($worktreeEntity->path, $name);
		}

		$this->databaseDialog = null;
	}

	public function startUpstream(): void
	{
		$this->upstreamChoice = $this->openWorktree()?->upstream ?? '';
		$this->upstreamDialog = true;
	}

	public function saveUpstream(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity !== null) {
			SetUpstreamAction::execute($worktreeEntity->path, $this->upstreamChoice === '' ? null : $this->upstreamChoice);
		}

		$this->upstreamDialog = false;
	}

	public function startTarget(): void
	{
		$this->targetChoice = $this->openWorktree()?->targetBranch ?? '';
		$this->targetDialog = true;
	}

	public function saveTarget(): void
	{
		$this->retargetTo(trim($this->targetChoice));

		$this->targetDialog = $this->failure !== null;
	}

	/**
	 * Puts the task on another branch to build on and merge into, in a process
	 * of its own: the pull request follows, and the branch is rebased onto it.
	 */
	public function retargetTo(string $target): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$isKnown = $target === $worktreeEntity->mainBranch
			|| in_array('origin/' . $target, resolve(WorktreeRepositoryContract::class)->getRemoteBranches($worktreeEntity->path), true);

		$this->failure = match (true) {
			$worktreeEntity->isRebasing => __('Er staat een rebase open. Maak die eerst af of breek hem af.'),
			$target === $worktreeEntity->branch => __('Een branch kan niet op zichzelf voortbouwen.'),
			! $isKnown => __(':target staat niet op origin. Push die branch eerst, dan kan deze taak erop.', ['target' => $target]),
			default => null,
		};

		if ($this->failure !== null || $target === $worktreeEntity->targetBranch) {
			return;
		}

		$this->failure = $this->startLinking(new WorkingCopyTaskObject(
			card: (string) $this->openCard,
			task: WorkingCopyTaskEnum::RETARGET,
			project: $worktreeEntity->project,
			branch: $worktreeEntity->branch,
			path: $worktreeEntity->path,
			ticketKey: $this->cardById($this->board(), (string) $this->openCard)?->ticketKey,
			target: $target,
		));
		$this->committed = null;
	}

	public function carryOverAgain(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity !== null && CarryOverAgainAction::execute($worktreeEntity)) {
			$this->committed = __('Opnieuw overgenomen uit het project.');
		}
	}

	/**
	 * Puts the project back on the main line and gives the branch a working
	 * copy of its own, so the agent no longer works next to you in the project.
	 * Refused while the project has uncommitted work.
	 */
	public function moveToOwnWorkingCopy(bool $carryChanges = false): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

		if ($cardObject?->project === null || $cardObject->branch === null) {
			return;
		}

		$this->failure = $this->startLinking(new WorkingCopyTaskObject(
			card: $cardObject->id,
			task: WorkingCopyTaskEnum::LINK,
			project: $cardObject->project,
			branch: $cardObject->branch,
			carriesChanges: $carryChanges,
		));
	}

	/**
	 * Makes the working copy of this card in a process of its own; the card
	 * follows it from there. Returns why it could not start, or null.
	 */
	protected function startLinking(WorkingCopyTaskObject $workingCopyTaskObject): ?string
	{
		return StartWorkingCopyTaskAction::execute($workingCopyTaskObject)
			? null
			: __('Er loopt al een taak op de werkkopie van deze kaart.');
	}

	/**
	 * Takes the message of a failed background task off the card.
	 */
	public function dismissWorkingCopyTask(): void
	{
		ForgetWorkingCopyTaskAction::execute((string) $this->openCard);
	}

	/**
	 * Brings what the finished tasks on the open card's working copy found
	 * into the panel: the last message, a failure, and what they wrote. The
	 * moment of the last ending taken travels with the page, so an answer the
	 * page never received is handed over again on the next request. It also
	 * picks up what finished while the panel was closed.
	 */
	public function followWorkingCopyTask(): void
	{
		if ($this->openCard === null) {
			return;
		}

		foreach (TakeWorkingCopyTaskEndingsAction::execute($this->openCard, $this->seenEndingsUntil) as $ending) {
			$this->seenEndingsUntil = max($this->seenEndingsUntil, $ending['at']);
			$this->failure = $ending['failure'] !== null
				? WorkingCopyTaskEnum::from($ending['task'])->failedLabel() . ': ' . $ending['failure']
				: $this->failure;
			$this->committed = $ending['notice'] ?? $this->committed;
			$this->commitMessage = $ending['result']['title'] ?? $this->commitMessage;
			$this->commitBody = $ending['result']['body'] ?? $this->commitBody;
			$this->newBranch = $ending['result']['branch'] ?? $this->newBranch;

			if (isset($ending['result']['tab'])) {
				$this->setDrawerTab($ending['result']['tab']);
			}
		}
	}

	/**
	 * Hands a slow button's work on a working copy to a process of its own,
	 * so the panel stays free while git or a helper is busy.
	 *
	 * @param  list<string>  $files
	 */
	protected function startWorkingCopyTask(WorkingCopyTaskEnum $workingCopyTaskEnum, WorktreeEntity $worktreeEntity, ?string $ticketKey = null, array $files = []): void
	{
		$this->failure = $this->startLinking(new WorkingCopyTaskObject(
			card: (string) $this->openCard,
			task: $workingCopyTaskEnum,
			project: $worktreeEntity->project,
			branch: $worktreeEntity->branch,
			path: $worktreeEntity->path,
			ticketKey: $ticketKey,
			files: $files,
		));
		$this->committed = null;
	}

	/**
	 * Everything the Werkkopie tab shows about the copy of this card.
	 *
	 * @param  Collection<int, ChangeObject>  $changes
	 * @return array<string, mixed>
	 */
	protected function workingCopyData(CardObject $cardObject, ?WorktreeEntity $worktreeEntity, Collection $changes): array
	{
		if ($worktreeEntity === null) {
			$externalPlace = $this->externalPlace($cardObject);

			return [
				'worktree' => null,
				'externalPlace' => $externalPlace,
				'mainCheckout' => ($externalPlace['isProject'] ?? false) ? $this->mainCheckout($cardObject) : null,
			];
		}

		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;
		$branchChain = $this->branchChainFor($worktreeEntity);

		$view = $this->diffViewFor($worktreeEntity);
		$viewChanges = collect($worktreeRepository->getChangesFor($worktreeEntity->path, $view));
		$picked = $changes->reject(fn (ChangeObject $changeObject) => in_array($changeObject->path, $this->skippedFiles, true));

		return [
			'worktree' => $worktreeEntity,
			'unpushed' => $worktreeRepository->getUnpushedCommits($worktreeEntity->path),
			'lastRebase' => $worktreeRepository->getLastRebase($worktreeEntity->path),
			'openConflicts' => $worktreeEntity->isRebasing ? $worktreeRepository->getConflictedFiles($worktreeEntity->path) : [],
			'lostByOverwrite' => $worktreeEntity->onlyOnUpstream > 0 ? $worktreeRepository->getUpstreamCommitsLostByOverwrite($worktreeEntity->path) : [],
			'branchChain' => $branchChain,
			'blockingLink' => $branchChain?->blockingLink(),
			'levelFirst' => $branchChain?->blockingLink() === null ? null : $this->levelFirst($branchChain->blockingLink()),
			'mainCheckout' => $worktreeEntity->origin === WorktreeOriginEnum::PROJECT ? $this->mainCheckout($cardObject) : null,
			'outdatedRules' => $this->outdatedRules($cardObject, $worktreeEntity),
			'changeCount' => $changes->count(),
			'pickedCount' => $picked->count(),
			'added' => $picked->sum('added'),
			'removed' => $picked->sum('removed'),
			'database' => $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describe($projectPath, $worktreeEntity->path),
			'testDatabase' => $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describeTesting($projectPath, $worktreeEntity->path),
			'databases' => $this->databaseDialog === 'choose' ? resolve(WorktreeDatabaseRepositoryContract::class)->getDatabases($worktreeEntity->path) : [],
			'remoteBranches' => $this->upstreamDialog || $this->targetDialog ? $worktreeRepository->getRemoteBranches($worktreeEntity->path) : [],
			'taskTargets' => $this->targetDialog ? $this->taskTargets($worktreeEntity, $worktreeRepository->getRemoteBranches($worktreeEntity->path)) : collect(),
			'packages' => $projectPath === null ? [] : resolve(WorkingCopyRepositoryContract::class)->getPackages($projectPath, $worktreeEntity->path),
			'carried' => resolve(WorkingCopyRepositoryContract::class)->getCarried($worktreeEntity->path),
			'fills' => DatabaseFillEnum::cases(),
			'cleanUp' => DescribeCleanUpAction::execute($worktreeEntity),
			'copyView' => $view,
			'viewCounts' => $this->diffViewCounts($worktreeEntity),
			'newCommitCount' => $worktreeRepository->getNewCommitCount($worktreeEntity->path),
			'viewChanges' => ($this->showAllChanges ? $viewChanges : $viewChanges->take(self::CHANGES_SHOWN))->map(fn (ChangeObject $changeObject) => [
				'change' => $changeObject,
				'isPicked' => ! in_array($changeObject->path, $this->skippedFiles, true),
			]),
			'hiddenViewChanges' => $this->showAllChanges ? 0 : max(0, $viewChanges->count() - self::CHANGES_SHOWN),
			'viewAdded' => $viewChanges->sum('added'),
			'viewRemoved' => $viewChanges->sum('removed'),
		];
	}

	/**
	 * The library rules this copy holds an older version of.
	 *
	 * @return array<int, StandingObject>
	 */
	private function outdatedRules(CardObject $cardObject, WorktreeEntity $worktreeEntity): array
	{
		return collect(GetLibrariesAction::execute())
			->filter(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy((string) $cardObject->project))
			->flatMap(fn (LibraryEntity $libraryEntity) => GetStandingsAction::execute($libraryEntity, [(string) $cardObject->project], null, $worktreeEntity->path))
			->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::BEHIND)
			->values()
			->all();
	}

	/**
	 * The folder where the branch of this card stands checked out without
	 * DevDock knowing it, with whether that folder is the project itself.
	 *
	 * @return array{path: string, isProject: bool}|null
	 */
	private function externalPlace(CardObject $cardObject): ?array
	{
		$projectPath = $this->projectPathFor($cardObject);

		if ($projectPath === null || $cardObject->branch === null) {
			return null;
		}

		$path = resolve(WorktreeRepositoryContract::class)->getPlaces($projectPath)[$cardObject->branch] ?? null;

		return $path === null ? null : [
			'path' => $path,
			'isProject' => strcasecmp(rtrim(str_replace('\\', '/', $path), '/'), rtrim(str_replace('\\', '/', $projectPath), '/')) === 0,
		];
	}

	/**
	 * The project folder this branch is checked out in, with what is not
	 * committed there yet.
	 *
	 * @return array{path: string, folder: string, uncommitted: array<int, string>}|null
	 */
	private function mainCheckout(CardObject $cardObject): ?array
	{
		$projectPath = $this->projectPathFor($cardObject);

		return $projectPath === null || $cardObject->branch === null ? null : [
			'path' => $projectPath,
			'folder' => trim((string) config('devdock.worktree.folder'), '/') . '/' . basename(EnsureWorktreeAction::folderFor($projectPath, $cardObject->branch)),
			'uncommitted' => resolve(WorktreeRepositoryContract::class)->getUncommittedFiles($projectPath),
		];
	}

	private function projectPathFor(CardObject $cardObject): ?string
	{
		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $cardObject->project)
			?->path;
	}

	private function describeDatabase(WorktreeEntity $worktreeEntity): ?DatabaseObject
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		return $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describe($projectPath, $worktreeEntity->path);
	}

	/**
	 * Reads how the branch stands against its target again, so the card shows
	 * it right away instead of after the next fetch round.
	 */
	private function refreshStanding(WorktreeEntity $worktreeEntity): void
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		if ($projectPath !== null) {
			resolve(BranchStandingRepositoryContract::class)->warm($projectPath, [$worktreeEntity->branch], again: true);
		}
	}

	/**
	 * The changed files that are ticked to go along in the next commit.
	 *
	 * @return list<string>
	 */
	protected function filesToCommit(WorktreeEntity $worktreeEntity): array
	{
		return collect(resolve(WorktreeRepositoryContract::class)->getChanges($worktreeEntity->path))
			->map(fn (ChangeObject $changeObject): string => $changeObject->path)
			->reject(fn (string $path): bool => in_array($path, $this->skippedFiles, true))
			->values()
			->all();
	}

	/**
	 * The stack of pull requests the branch of a working copy stands in.
	 */
	private function branchChainFor(WorktreeEntity $worktreeEntity): ?BranchChainObject
	{
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity): bool => $projectEntity->name === $worktreeEntity->project);

		return $projectEntity === null
			? null
			: GetBranchChainAction::execute($projectEntity->path, $projectEntity->repositorySlug(), $worktreeEntity->branch, $worktreeEntity->mainBranch, $worktreeEntity->targetBranch);
	}

	/**
	 * The other tasks of the same project whose branch is on origin, as
	 * branches this task can build on.
	 *
	 * @param  array<int, string>  $remoteBranches
	 * @return Collection<int, CardObject>
	 */
	private function taskTargets(WorktreeEntity $worktreeEntity, array $remoteBranches): Collection
	{
		return $this->board()
			->filter(fn (CardObject $cardObject): bool => $cardObject->project === $worktreeEntity->project
				&& $cardObject->branch !== null
				&& $cardObject->branch !== $worktreeEntity->branch
				&& in_array('origin/' . $cardObject->branch, $remoteBranches, true))
			->unique(fn (CardObject $cardObject): string => (string) $cardObject->branch)
			->values();
	}

	private function levelFirst(BranchLinkObject $branchLinkObject): string
	{
		return trans_choice('Werk eerst :branch bij: die loopt :count commit achter op :target. Daarna kan deze branch erop.|Werk eerst :branch bij: die loopt :count commits achter op :target. Daarna kan deze branch erop.', $branchLinkObject->behind, [
			'branch' => $branchLinkObject->branch,
			'count' => $branchLinkObject->behind,
			'target' => $branchLinkObject->target,
		]);
	}

	private function openWorktree(): ?WorktreeEntity
	{
		return $this->worktreeFor($this->cardById($this->board(), (string) $this->openCard));
	}
}
