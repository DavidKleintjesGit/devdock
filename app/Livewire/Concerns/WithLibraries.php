<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Collection;
use Src\Library\Actions\CreateLibraryAction;
use Src\Library\Actions\DiffTextsAction;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\GetStandingsAction;
use Src\Library\Actions\ImportFolderAction;
use Src\Library\Actions\RollOutAction;
use Src\Library\Actions\SaveVersionAction;
use Src\Library\Actions\WriteToFolderAction;
use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\StandingEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryItemEntity;
use Src\Library\Objects\StandingObject;
use Src\Project\Actions\GetProjectsAction;

/**
 * What the Rules and Skills pages share about libraries: the drawer of one
 * library item with its versions, adding a library, and rolling a version
 * out to projects. Writing to a project or a folder only ever happens here,
 * on a press.
 */
trait WithLibraries
{
	public ?string $libraryItem = null;

	public string $libraryTab = 'Inhoud';

	public bool $editing = false;

	public string $editContents = '';

	public string $editNote = '';

	/** @var array<int, int> */
	public array $pickedVersions = [];

	public bool $addingLibrary = false;

	public string $newLibraryName = '';

	public string $newLibraryStart = 'folder';

	public string $newLibraryFolder = '';

	public string $newLibrarySkillsFolder = '';

	public bool $keepLinked = true;

	/** @var array<int, string> */
	public array $newLibraryFollowers = [];

	/** @var array<int, string> */
	public array $rollOutProjects = [];

	public bool $confirmingRollOut = false;

	public ?string $rollOutItem = null;

	public ?string $libraryDone = null;

	abstract protected function libraryKind(): LibraryItemKindEnum;

	public function openLibraryItem(int $libraryId, string $name): void
	{
		$this->libraryItem = $libraryId . ':' . $name;
		$this->libraryTab = 'Inhoud';
		$this->editing = false;
		$this->pickedVersions = [];
	}

	public function closeLibraryItem(): void
	{
		$this->libraryItem = null;
		$this->editing = false;
	}

	public function startEdit(): void
	{
		$this->editContents = (string) $this->openItem()?->latest()?->contents;
		$this->editNote = '';
		$this->editing = true;
		$this->libraryTab = 'Inhoud';
	}

	/**
	 * Saves the edited content as the next version. Every follower is behind
	 * from then on, until you roll it out.
	 */
	public function saveEdit(): void
	{
		$itemEntity = $this->openItem();

		if ($itemEntity === null) {
			return;
		}

		SaveVersionAction::execute($itemEntity->libraryId, $itemEntity->kind, $itemEntity->name, VersionSourceEnum::EDIT, $this->editContents, $this->editNote ?: null);

		$this->editing = false;
		$this->libraryDone = __('Opgeslagen als nieuwe versie.');
	}

	public function togglePickedVersion(int $number): void
	{
		$this->pickedVersions = in_array($number, $this->pickedVersions, true)
			? array_values(array_diff($this->pickedVersions, [$number]))
			: array_slice([...$this->pickedVersions, $number], -2);
	}

	/**
	 * Takes an earlier version up again as the newest. The history stays as
	 * it is.
	 */
	public function restoreVersion(int $number): void
	{
		$itemEntity = $this->openItem();
		$versionEntity = $itemEntity?->versionNumbered($number);

		if ($versionEntity === null) {
			return;
		}

		SaveVersionAction::execute($itemEntity->libraryId, $itemEntity->kind, $itemEntity->name, VersionSourceEnum::RESTORE, $versionEntity->contents, __('Terug naar :version', ['version' => $versionEntity->label()]));

		$this->libraryDone = __(':version is teruggehaald als nieuwste versie.', ['version' => $versionEntity->label()]);
	}

	/**
	 * Takes what stands in a project back into the library as a new version.
	 */
	public function pushBack(int $libraryId, string $name, string $project): void
	{
		SaveVersionAction::execute($libraryId, $this->libraryKind(), $name, VersionSourceEnum::PROJECT, null, __('Teruggezet vanuit :project', ['project' => $project]), $project);

		$this->libraryDone = __('Teruggezet naar de bibliotheek.');
	}

	/**
	 * @param  array<int, string>  $projects
	 */
	public function askRollOut(array $projects, ?string $item = null): void
	{
		$this->rollOutItem = $item ?? $this->libraryItem;
		$this->rollOutProjects = array_values($projects);
		$this->confirmingRollOut = $this->rollOutProjects !== [];
	}

	public function toggleRollOutProject(string $project): void
	{
		$this->rollOutProjects = in_array($project, $this->rollOutProjects, true)
			? array_values(array_diff($this->rollOutProjects, [$project]))
			: [...$this->rollOutProjects, $project];
	}

	/**
	 * Puts the newest version down in the projects you confirmed. What stood
	 * there is kept first.
	 */
	public function rollOut(): void
	{
		$itemEntity = $this->itemFor($this->rollOutItem);

		if ($itemEntity === null) {
			return;
		}

		$written = RollOutAction::execute($itemEntity, $this->rollOutProjects);

		$this->confirmingRollOut = false;
		$this->rollOutProjects = [];
		$this->libraryDone = trans_choice('Bijgewerkt in :count project. Committen doe je daar zelf.|Bijgewerkt in :count projecten. Committen doe je daar zelf.', $written, ['count' => $written]);
	}

	public function cancelRollOut(): void
	{
		$this->confirmingRollOut = false;
	}

	public function writeToFolder(int $libraryId): void
	{
		$written = WriteToFolderAction::execute($libraryId);

		$this->libraryDone = trans_choice(':count bestand naar de map geschreven.|:count bestanden naar de map geschreven.', $written, ['count' => $written]);
	}

	public function syncLibrary(int $libraryId): void
	{
		$libraryEntity = resolve(LibraryRepositoryContract::class)->getById($libraryId);
		$added = $libraryEntity === null ? 0 : ImportFolderAction::execute($libraryId, $libraryEntity->folder, $libraryEntity->skillsFolder);

		$this->libraryDone = $added === 0
			? __('Niets nieuws in de gekoppelde map.')
			: trans_choice(':count nieuwe versie binnengehaald.|:count nieuwe versies binnengehaald.', $added, ['count' => $added]);
	}

	public function startLibrary(): void
	{
		$this->addingLibrary = true;
		$this->newLibraryName = '';
		$this->newLibraryStart = 'folder';
		$this->newLibraryFolder = '';
		$this->newLibrarySkillsFolder = '';
		$this->keepLinked = true;
		$this->newLibraryFollowers = [];
		$this->resetValidation();
	}

	public function createLibrary(): void
	{
		$this->validate([
			'newLibraryName' => ['required', 'min:2', 'max:60'],
		], [
			'newLibraryName.required' => __('Geef de bibliotheek een naam.'),
		]);

		$fromFolder = $this->newLibraryStart === 'folder';

		CreateLibraryAction::execute(
			trim($this->newLibraryName),
			$fromFolder ? $this->newLibraryFolder : null,
			$fromFolder ? $this->newLibrarySkillsFolder : null,
			$fromFolder && $this->keepLinked,
			$this->newLibraryFollowers,
		);

		$this->addingLibrary = false;
		$this->libraryDone = __('Bibliotheek toegevoegd.');
	}

	public function toggleNewFollower(string $project): void
	{
		$this->newLibraryFollowers = in_array($project, $this->newLibraryFollowers, true)
			? array_values(array_diff($this->newLibraryFollowers, [$project]))
			: [...$this->newLibraryFollowers, $project];
	}

	/**
	 * What the add-a-library window shows: what the folder holds, and which
	 * projects already hold the same files.
	 *
	 * @return array<string, mixed>
	 */
	protected function newLibraryData(): array
	{
		if (! $this->addingLibrary) {
			return [];
		}

		$disk = resolve(LibraryDiskRepositoryContract::class);
		$folder = rtrim(str_replace('\\', '/', trim($this->newLibraryFolder)), '/');
		$found = $folder === '' ? [] : array_keys($disk->readFolder($folder, LibraryItemKindEnum::RULE));
		$skillsFolder = rtrim(str_replace('\\', '/', trim($this->newLibrarySkillsFolder)), '/');

		return [
			'foundRules' => $found,
			'foundRuleNames' => implode(', ', array_map(fn (string $name) => pathinfo($name, PATHINFO_FILENAME), $found)),
			'foundSkills' => $skillsFolder === '' ? [] : array_keys($disk->readFolder($skillsFolder, LibraryItemKindEnum::SKILL)),
			'matchingProjects' => $found === [] ? [] : $this->projectsHolding($disk, $found),
		];
	}

	/**
	 * Everything the drawer of one library item shows.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function libraryItemData(): ?array
	{
		$itemEntity = $this->openItem();

		if ($itemEntity === null) {
			return null;
		}

		$libraryEntity = resolve(LibraryRepositoryContract::class)->getById($itemEntity->libraryId);
		$standings = collect(GetStandingsAction::execute($libraryEntity, null, $itemEntity));
		$latest = $itemEntity->latest();
		$picked = collect($this->pickedVersions)->sort()->values();

		return [
			'item' => $itemEntity,
			'library' => $libraryEntity,
			'latest' => $latest,
			'versions' => array_reverse($itemEntity->versions),
			'standings' => $standings,
			'onVersion' => $standings
				->filter(fn (StandingObject $standingObject) => $standingObject->onVersion !== null)
				->groupBy(fn (StandingObject $standingObject) => $standingObject->onVersion->number)
				->map(fn (Collection $group) => $group->pluck('project')->all())
				->all(),
			'rollouts' => resolve(LibraryRepositoryContract::class)->getRollouts($itemEntity->id),

			// behind or missing; a project that changed it itself is never overwritten from here
			'rollOutCandidates' => $standings
				->filter(fn (StandingObject $standingObject) => in_array($standingObject->standing, [StandingEnum::BEHIND, StandingEnum::MISSING], true))
				->pluck('project')
				->values()
				->all(),
			'versionDiff' => $picked->count() === 2
				? DiffTextsAction::execute(
					(string) $itemEntity->versionNumbered($picked[0])?->contents,
					(string) $itemEntity->versionNumbered($picked[1])?->contents,
				)
				: null,
			'pickedLabel' => $picked->map(fn (int $number) => 'v' . $number)->implode(__(' en ')),
		];
	}

	/**
	 * How one library item stands in every project, grouped the way the page
	 * of that item shows it.
	 *
	 * @return array<string, mixed>
	 */
	protected function itemStandings(LibraryEntity $libraryEntity, LibraryItemEntity $itemEntity): array
	{
		$standings = collect(GetStandingsAction::execute($libraryEntity, null, $itemEntity));

		return [
			'behind' => $standings->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::BEHIND)->values(),
			'modified' => $standings->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::MODIFIED)->values(),
			'missing' => $standings->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::MISSING)->values(),
			'equal' => $standings->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::EQUAL)->values(),
		];
	}

	protected function openItem(): ?LibraryItemEntity
	{
		return $this->itemFor($this->libraryItem);
	}

	/**
	 * The item behind a key of library id and name.
	 */
	protected function itemFor(?string $key): ?LibraryItemEntity
	{
		if ($key === null) {
			return null;
		}

		[$libraryId, $name] = explode(':', $key, 2) + [1 => ''];

		return collect(GetLibrariesAction::execute())
			->first(fn (LibraryEntity $libraryEntity) => $libraryEntity->id === (int) $libraryId)
			?->item($this->libraryKind(), $name);
	}

	/**
	 * @param  array<int, string>  $names
	 * @return array<string, int>
	 */
	private function projectsHolding(LibraryDiskRepositoryContract $disk, array $names): array
	{
		$matches = [];

		foreach (GetProjectsAction::execute() as $projectEntity) {
			$files = $disk->projectFiles($projectEntity->path, LibraryItemKindEnum::RULE);
			$count = count(array_intersect(array_keys($files), $names));

			if ($count > 0) {
				$matches[$projectEntity->name] = $count;
			}
		}

		arsort($matches);

		return $matches;
	}
}
