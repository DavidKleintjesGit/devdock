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
use Src\Worktree\Actions\CommitChangesAction;
use Src\Worktree\Actions\CreateWorktreeDatabaseAction;
use Src\Worktree\Actions\DescribeCleanUpAction;
use Src\Worktree\Actions\PushBranchAction;
use Src\Worktree\Actions\SetUpstreamAction;
use Src\Worktree\Actions\UpdateWorktreeAction;
use Src\Worktree\Actions\UseWorktreeDatabaseAction;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\DatabaseObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The Werkkopie tab of a card: bringing the branch up to date, picking what
 * goes into a commit, pushing, and the database and upstream of the copy.
 */
trait WithWorkingCopy
{
	/** @var array<int, string> the changed files that stay out of the next commit */
	public array $skippedFiles = [];

	public bool $pushAfterCommit = true;

	public bool $showAllChanges = false;

	public bool $showAllBranchChanges = false;

	/**
	 * How many files of the branch show before "Nog n bestanden".
	 */
	private const BRANCH_CHANGES_SHOWN = 4;

	public ?string $databaseDialog = null;

	public string $databaseName = '';

	public string $databaseFill = 'copy';

	public bool $upstreamDialog = false;

	public string $upstreamChoice = '';

	public function updateFromMain(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$failure = UpdateWorktreeAction::execute($worktreeEntity->path);

		$this->failure = $failure;
		$this->committed = $failure === null ? __('Bijgewerkt met :main.', ['main' => $worktreeEntity->mainBranch]) : null;
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

		$files = collect(resolve(WorktreeRepositoryContract::class)->getChanges($worktreeEntity->path))
			->map(fn (ChangeObject $changeObject) => $changeObject->path)
			->reject(fn (string $path) => in_array($path, $this->skippedFiles, true))
			->values()
			->all();

		if ($files === []) {
			$this->failure = __('Er is niets aangevinkt om te committen.');

			return;
		}

		if (! CommitChangesAction::execute($worktreeEntity->path, $this->commitMessage, $this->commitBody, $files)) {
			$this->failure = __('Committen lukte niet.');

			return;
		}

		$this->committed = trans_choice('Vastgelegd: :count bestand.|Vastgelegd: :count bestanden.', count($files), ['count' => count($files)]);
		$this->commitMessage = '';
		$this->commitBody = '';
		$this->skippedFiles = [];

		if ($this->pushAfterCommit) {
			$this->pushBranch();
		}
	}

	public function pushBranch(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$pushed = PushBranchAction::execute($worktreeEntity->path);

		$this->committed = $pushed ? __('Gepusht naar origin.') : null;
		$this->failure = $pushed ? null : __('Pushen lukte niet. Een taakbranch pusht nooit naar de hoofdlijn.');
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

	public function carryOverAgain(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity !== null && CarryOverAgainAction::execute($worktreeEntity)) {
			$this->committed = __('Opnieuw overgenomen uit het project.');
		}
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
			return ['worktree' => null];
		}

		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		$branchChanges = collect($worktreeRepository->getBranchChanges($worktreeEntity->path));
		$picked = $changes->reject(fn (ChangeObject $changeObject) => in_array($changeObject->path, $this->skippedFiles, true));

		return [
			'worktree' => $worktreeEntity,
			'unpushed' => $worktreeRepository->getUnpushedCommits($worktreeEntity->path),
			'outdatedRules' => $this->outdatedRules($cardObject, $worktreeEntity),
			'changes' => ($this->showAllChanges ? $changes : $changes->take(4))->map(fn (ChangeObject $changeObject) => [
				'change' => $changeObject,
				'isPicked' => ! in_array($changeObject->path, $this->skippedFiles, true),
			]),
			'hiddenChanges' => $this->showAllChanges ? 0 : max(0, $changes->count() - 4),
			'changeCount' => $changes->count(),
			'pickedCount' => $picked->count(),
			'added' => $picked->sum('added'),
			'removed' => $picked->sum('removed'),
			'database' => $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describe($projectPath, $worktreeEntity->path),
			'databases' => $this->databaseDialog === 'choose' ? resolve(WorktreeDatabaseRepositoryContract::class)->getDatabases($worktreeEntity->path) : [],
			'remoteBranches' => $this->upstreamDialog ? $worktreeRepository->getRemoteBranches($worktreeEntity->path) : [],
			'packages' => $projectPath === null ? [] : resolve(WorkingCopyRepositoryContract::class)->getPackages($projectPath, $worktreeEntity->path),
			'carried' => resolve(WorkingCopyRepositoryContract::class)->getCarried($worktreeEntity->path),
			'fills' => DatabaseFillEnum::cases(),
			'cleanUp' => DescribeCleanUpAction::execute($worktreeEntity),
			'branchChanges' => $this->showAllBranchChanges ? $branchChanges : $branchChanges->take(self::BRANCH_CHANGES_SHOWN),
			'hiddenBranchChanges' => $this->showAllBranchChanges ? 0 : max(0, $branchChanges->count() - self::BRANCH_CHANGES_SHOWN),
			'branchChangeCount' => $branchChanges->count(),
			'branchAdded' => $branchChanges->sum('added'),
			'branchRemoved' => $branchChanges->sum('removed'),
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

	private function describeDatabase(WorktreeEntity $worktreeEntity): ?DatabaseObject
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		return $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describe($projectPath, $worktreeEntity->path);
	}

	private function openWorktree(): ?WorktreeEntity
	{
		return $this->worktreeFor($this->cardById($this->board(), (string) $this->openCard));
	}
}
