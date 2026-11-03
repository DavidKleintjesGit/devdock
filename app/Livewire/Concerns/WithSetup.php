<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Board\Objects\CardObject;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Actions\GetBranchChoicesAction;
use Src\Worktree\Actions\LinkWorkingCopyAction;
use Src\Worktree\Enums\BranchPlaceEnum;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Objects\BranchChoiceObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Setting a task up in three steps: a project, a working copy on a branch,
 * and an agent that works in it.
 */
trait WithSetup
{
	/**
	 * The branch picked for the working copy; NEW_BRANCH means the name in
	 * the field below it.
	 */
	public string $setupBranch = '';

	private const NEW_BRANCH = '__new__';

	/**
	 * Sets the working copy ready on the picked branch, and ties the task to
	 * it.
	 */
	public function prepareWorkingCopy(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$branch = $this->setupBranch === self::NEW_BRANCH || $this->setupBranch === '' ? trim($this->newBranch) : $this->setupBranch;

		if ($cardObject === null || $cardObject->project === null) {
			$this->failure = __('Zonder project weet ik niet in welke checkout de werkkopie moet komen.');

			return;
		}

		if ($branch === '') {
			$this->failure = __('Kies een branch of vul de naam van een nieuwe in.');

			return;
		}

		$this->failure = LinkWorkingCopyAction::execute(
			$cardObject->id,
			$cardObject->project,
			$branch,
			$this->chosenFill(),
		);
	}

	/**
	 * Gives the task its agent in the working copy that now stands, and goes
	 * straight to it: the panel is done, the agent waits for its first message.
	 */
	public function assignAgent(): void
	{
		$this->openAgent();
		$this->setDrawerTab('Agent');
	}

	/**
	 * Starts the setup of a card on the branch it already has, or else the
	 * one carrying its ticket number, or else a new one.
	 */
	protected function resetSetup(?CardObject $cardObject): void
	{
		$this->setupBranch = '';

		if ($cardObject === null || $cardObject->project === null) {
			return;
		}

		if ($cardObject->branch !== null) {
			$this->setupBranch = $cardObject->branch;

			return;
		}

		$match = collect(GetBranchChoicesAction::execute($cardObject->project, $cardObject->ticketKey))
			->first(fn (BranchChoiceObject $branchChoiceObject) => $branchChoiceObject->matchesTicket);

		$this->setupBranch = $match?->name ?? self::NEW_BRANCH;
	}

	/**
	 * Everything the setup panel shows.
	 *
	 * @return array<string, mixed>
	 */
	protected function setupData(CardObject $cardObject, ?WorktreeEntity $worktreeEntity): array
	{
		$choices = $cardObject->project !== null && $worktreeEntity === null
			? GetBranchChoicesAction::execute($cardObject->project, $cardObject->ticketKey)
			: [];
		$chosen = collect($choices)->first(fn (BranchChoiceObject $branchChoiceObject) => $branchChoiceObject->name === $this->setupBranch);

		// een vastgezette branch die nergens meer staat komt toch in de lijst
		if ($chosen === null && ! in_array($this->setupBranch, ['', self::NEW_BRANCH], true) && $choices !== []) {
			$chosen = new BranchChoiceObject($this->setupBranch, BranchPlaceEnum::LOCAL, null, false);
			$choices = [$chosen, ...$choices];
		}

		$steps = [
			['key' => 'project', 'label' => __('Project'), 'isDone' => $cardObject->project !== null],
			['key' => 'worktree', 'label' => __('Werkkopie'), 'isDone' => $worktreeEntity !== null],
			['key' => 'agent', 'label' => __('Agent'), 'isDone' => $cardObject->agent !== null],
		];

		return [
			'setupSteps' => $steps,
			'setupHint' => $this->setupHintFor($steps),
			'workingCopyChoices' => $choices,
			'newBranchChosen' => $chosen === null,
			'preparePlan' => $worktreeEntity === null && $cardObject->project !== null ? $this->preparePlan($cardObject, $chosen) : null,
			'databaseModes' => [
				'own' => __('Eigen, kopie van het project'),
				'migrate' => __('Eigen, leeg met migraties'),
				'empty' => __('Eigen, helemaal leeg'),
			],
		];
	}

	/**
	 * What pressing Klaarzetten will do, in one sentence, and whether it
	 * makes something new so the database can still be chosen.
	 *
	 * @return array{icon: string, text: string, isNew: bool, isProject: bool}
	 */
	private function preparePlan(CardObject $cardObject, ?BranchChoiceObject $chosen): array
	{
		$place = $chosen?->place;

		if ($place === BranchPlaceEnum::WORKTREE) {
			return ['icon' => 'branch', 'text' => __('Gebruikt de werkkopie die er al is: :path.', ['path' => $chosen->path]), 'isNew' => false, 'isProject' => false];
		}

		if ($place === BranchPlaceEnum::PROJECT) {
			return [
				'icon' => 'alert',
				'text' => __('Deze branch staat open in het project zelf. Het project gaat eerst terug naar :main, daarna krijgt de branch een eigen werkkopie. Dat wordt geweigerd zolang er in het project werk staat dat niet gecommit is.', ['main' => $cardObject->mainBranch ?? 'master']),
				'isNew' => true,
				'isProject' => true,
			];
		}

		$branch = $chosen?->name ?? trim($this->newBranch);
		$projectPath = (string) $this->projectPathOf($cardObject->project);
		$path = $branch === '' ? null : EnsureWorktreeAction::folderFor($projectPath, $branch);
		$database = (string) $this->databaseSuggestionFor($cardObject);

		$text = match ($this->newDatabaseMode) {
			'migrate' => __('Maakt een nieuwe werkkopie in :path met database :database, leeg en daarna de migraties.', ['path' => $path ?? '…', 'database' => $database]),
			'empty' => __('Maakt een nieuwe werkkopie in :path met een lege database :database.', ['path' => $path ?? '…', 'database' => $database]),
			default => __('Maakt een nieuwe werkkopie in :path met database :database, een kopie van het project.', ['path' => $path ?? '…', 'database' => $database]),
		};

		return [
			'icon' => 'folder',
			'text' => $text . ' ' . __('Daarna maakt DevDock hem in de achtergrond compleet: pakketten, assets, testdatabase, site.'),
			'isNew' => true,
			'isProject' => false,
		];
	}

	/**
	 * @param  array<int, array{key: string, label: string, isDone: bool}>  $steps
	 */
	private function setupHintFor(array $steps): string
	{
		$open = collect($steps)->search(fn (array $step) => ! $step['isDone']);

		if ($open === false) {
			return __('Alles staat; de agent kan beginnen.');
		}

		return __('Stap :number van :total', ['number' => $open + 1, 'total' => count($steps)]);
	}

	/**
	 * The database a new working copy starts with: a copy of the project's
	 * unless you chose an empty or a migrated one.
	 */
	private function chosenFill(): ?DatabaseFillEnum
	{
		return match ($this->newDatabaseMode) {
			'migrate' => DatabaseFillEnum::MIGRATE,
			'empty' => DatabaseFillEnum::EMPTY,
			default => null,
		};
	}

	private function projectPathOf(?string $project): ?string
	{
		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $project)
			?->path;
	}
}
