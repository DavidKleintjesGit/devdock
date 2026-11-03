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
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Actions\PushBranchAction;
use Src\Worktree\Actions\RebaseWorktreeAction;
use Src\Worktree\Actions\RemoveWorktreeAction;
use Src\Worktree\Actions\SetUpstreamAction;
use Src\Worktree\Actions\UseWorktreeDatabaseAction;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
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

	public function rebaseOnMain(): void
	{
		$worktreeEntity = $this->openWorktree();

		if ($worktreeEntity === null) {
			return;
		}

		$failure = RebaseWorktreeAction::execute($worktreeEntity->path);

		$this->failure = $failure;
		$this->committed = $failure === null ? __('Gerebased op :main.', ['main' => $worktreeEntity->mainBranch]) : null;

		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		// the card shows the new standing right away instead of after the next fetch round
		if ($projectPath !== null) {
			resolve(BranchStandingRepositoryContract::class)->warm($projectPath, [$worktreeEntity->branch], again: true);
		}
	}

	/**
	 * Gives an existing working copy a test database of its own, so its tests
	 * stop sharing one with the project and the other copies.
	 */
	public function giveOwnTestDatabase(): void
	{
		$worktreeEntity = $this->openWorktree();
		$projectPath = $worktreeEntity === null ? null : GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $worktreeEntity->project)
			?->path;

		if ($projectPath === null) {
			return;
		}

		$this->failure = resolve(WorktreeDatabaseRepositoryContract::class)->giveOwnTestDatabase($projectPath, $worktreeEntity->path, $worktreeEntity->branch);
		$this->committed = $this->failure === null ? __('Deze kopie test nu in een eigen database.') : null;
	}

	/**
	 * Asks the agent of this task to rebase and settle the clashing files.
	 */
	public function resolveConflictsWithAgent(): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

		if ($cardObject === null || ! $cardObject->hasConflict()) {
			return;
		}

		$this->prompt = __('Rebase deze branch op origin/:main en los de conflicten op in :files. Draai daarna de tests van wat je aanraakte. Push niet, zeg wanneer het klaar is.', [
			'main' => $cardObject->mainBranch ?? 'master',
			'files' => implode(', ', $cardObject->conflictedFiles),
		]);

		$this->send();
		$this->setDrawerTab('Agent');
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
		$this->failure = match (true) {
			$pushed => null,
			$worktreeEntity->onlyOnUpstream > 0 => __('Overschrijven lukte niet: origin veranderde sinds DevDock er voor het laatst keek. Haal eerst op en kijk wat er bij kwam.'),
			default => __('Pushen lukte niet. Een taakbranch pusht nooit naar de hoofdlijn.'),
		};
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
	 * Takes a folder where the branch of this task already stands checked out
	 * as its working copy: the project itself, or a copy made outside DevDock.
	 * Cleaning up leaves such a folder where it is.
	 */
	public function adoptExternal(): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$place = $cardObject === null ? null : $this->externalPlace($cardObject);

		if ($place === null) {
			$this->failure = __('De branch staat niet meer uitgecheckt; ververs even.');

			return;
		}

		resolve(WorktreeRepositoryContract::class)->adopt(
			(string) $cardObject->project,
			(string) $cardObject->branch,
			$place['path'],
			$place['isProject'] ? WorktreeOriginEnum::PROJECT : WorktreeOriginEnum::ADOPTED,
			$cardObject->id,
		);

		$this->committed = __('Gekoppeld aan DevDock.');
	}

	/**
	 * Puts the project back on the main line and gives the branch a working
	 * copy of its own, so the agent no longer works next to you in the project.
	 * Refused while the project has uncommitted work.
	 */
	public function moveToOwnWorkingCopy(bool $carryChanges = false): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$projectPath = $cardObject === null ? null : $this->projectPathFor($cardObject);

		if ($projectPath === null) {
			return;
		}

		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$this->failure = $worktreeRepository->switchToMain($projectPath, $carryChanges);

		if ($this->failure !== null) {
			return;
		}

		$linked = $this->worktreeFor($cardObject);

		// the link to the project itself goes; the folder stays as it is
		if ($linked !== null && $linked->origin === WorktreeOriginEnum::PROJECT) {
			RemoveWorktreeAction::execute($linked->worktreeId);
		}

		$this->createWorktree();

		$worktreeEntity = $this->openWorktree();

		if ($carryChanges && $this->failure === null && $worktreeEntity !== null) {
			$this->failure = $worktreeRepository->layBackCarried($worktreeEntity->path);
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

		$branchChanges = collect($worktreeRepository->getBranchChanges($worktreeEntity->path));
		$picked = $changes->reject(fn (ChangeObject $changeObject) => in_array($changeObject->path, $this->skippedFiles, true));

		return [
			'worktree' => $worktreeEntity,
			'unpushed' => $worktreeRepository->getUnpushedCommits($worktreeEntity->path),
			'lastRebase' => $worktreeRepository->getLastRebase($worktreeEntity->path),
			'mainCheckout' => $worktreeEntity->origin === WorktreeOriginEnum::PROJECT ? $this->mainCheckout($cardObject) : null,
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
			'testDatabase' => $projectPath === null ? null : resolve(WorktreeDatabaseRepositoryContract::class)->describeTesting($projectPath, $worktreeEntity->path),
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

	private function openWorktree(): ?WorktreeEntity
	{
		return $this->worktreeFor($this->cardById($this->board(), (string) $this->openCard));
	}
}
