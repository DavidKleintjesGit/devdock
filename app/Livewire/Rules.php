<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithLibraries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Library\Actions\DiffTextsAction;
use Src\Library\Actions\FollowLibraryAction;
use Src\Library\Actions\GetLandingAction;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\GetStandingsAction;
use Src\Library\Actions\RollOutAction;
use Src\Library\Actions\SaveVersionAction;
use Src\Library\Actions\SetLandingAction;
use Src\Library\Actions\ToggleFavoriteAction;
use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LandingEnum;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\StandingEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryItemEntity;
use Src\Library\Objects\StandingObject;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Rule\Actions\CopyRuleFileToProjectAction;
use Src\Rule\Actions\CreateRuleFileAction;
use Src\Rule\Actions\GetGlobalRulesAction;
use Src\Rule\Actions\GetPermissionsAction;
use Src\Rule\Actions\GetRuleFileContentsAction;
use Src\Rule\Actions\GetRuleFilesAction;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Objects\PermissionRuleObject;
use Src\Rule\Objects\RuleFileEntity;

class Rules extends Component
{
	use WithLibraries;

	/**
	 * How many projects the list shows before "Nog n projecten".
	 */
	private const PROJECTS_SHOWN = 12;

	/**
	 * A finished round of fetching brings the page up to date.
	 */
	protected $listeners = ['fetched' => '$refresh'];

	/**
	 * What the page on the right shows: a project, a library, one rule of a
	 * library, or your global rules.
	 */
	#[Url(except: '')]
	public string $project = '';

	#[Url(except: null)]
	public ?int $library = null;

	#[Url(except: '')]
	public string $rule = '';

	#[Url(except: false)]
	public bool $global = false;

	#[Url(except: '')]
	public string $file = '';

	#[Url(as: 'nieuw', except: false)]
	public bool $openNewLibrary = false;

	public string $search = '';

	public bool $onlyDeviating = false;

	public bool $allProjects = false;

	public string $fileTab = 'Inhoud';

	public string $compareWith = '';

	/** @var array<int, string> */
	public array $pickedProjects = [];

	public bool $addingRules = false;

	/** @var array<int, int> */
	public array $followPicks = [];

	/** @var array<int, string> */
	public array $filePicks = [];

	public string $otherSearch = '';

	public bool $choosingLanding = false;

	public string $landingChoice = '';

	public string $landingFolder = '';

	public bool $creatingRule = false;

	public string $newRuleName = '';

	public string $newRuleWhere = 'project';

	public string $newRuleStart = 'empty';

	public function mount(): void
	{
		if ($this->openNewLibrary) {
			$this->openNewLibrary = false;
			$this->startLibrary();
		}

		if ($this->project === '' && $this->library === null && ! $this->global) {
			$this->project = collect(GetLibrariesAction::execute())->flatMap(fn (LibraryEntity $libraryEntity) => $libraryEntity->followers)->first() ?? '';
		}
	}

	public function showProject(string $project): void
	{
		$this->resetPage();
		$this->project = $project;
	}

	public function showLibrary(int $library): void
	{
		$this->resetPage();
		$this->library = $library;
	}

	public function showRule(int $library, string $rule): void
	{
		$this->resetPage();
		$this->library = $library;
		$this->rule = $rule;
	}

	public function showGlobal(): void
	{
		$this->resetPage();
		$this->global = true;
	}

	public function openFile(string $path): void
	{
		$this->file = $path;
		$this->fileTab = 'Inhoud';
		$this->compareWith = '';
	}

	public function openFavorite(string $project, string $path): void
	{
		$this->showProject($project);
		$this->openFile($path);
	}

	public function compareWithProject(string $project): void
	{
		$this->compareWith = 'p:' . $project;
		$this->fileTab = 'Vergelijken';
	}

	public function closeFile(): void
	{
		$this->file = '';
	}

	public function toggleFavorite(string $project, string $path): void
	{
		ToggleFavoriteAction::execute(LibraryItemKindEnum::RULE, $project, $path);
	}

	public function follow(int $library, bool $isFollowing): void
	{
		if ($this->project !== '') {
			FollowLibraryAction::execute($library, $this->project, $isFollowing);
		}
	}

	/**
	 * Puts the newest library version over the open file of the project.
	 */
	public function updateFile(): void
	{
		$standingObject = $this->fileStanding();

		if ($standingObject !== null) {
			RollOutAction::execute($standingObject->item, [$this->project]);
			$this->libraryDone = __('Bijgewerkt vanuit de bibliotheek. De oude inhoud is bewaard.');
		}
	}

	public function pushBackFile(): void
	{
		$standingObject = $this->fileStanding();

		if ($standingObject !== null) {
			$this->pushBack($standingObject->item->libraryId, $standingObject->item->name, $this->project);
		}
	}

	public function togglePicked(string $project): void
	{
		$this->pickedProjects = in_array($project, $this->pickedProjects, true)
			? array_values(array_diff($this->pickedProjects, [$project]))
			: [...$this->pickedProjects, $project];
	}

	/**
	 * Picks every follower that runs behind on the rule of this page.
	 */
	public function pickBehind(): void
	{
		$libraryEntity = collect(GetLibrariesAction::execute())->first(fn (LibraryEntity $item) => $item->id === $this->library);
		$itemEntity = $libraryEntity?->item(LibraryItemKindEnum::RULE, $this->rule);

		$this->pickedProjects = $itemEntity === null ? [] : $this->itemStandings($libraryEntity, $itemEntity)['behind']->pluck('project')->values()->all();
	}

	/**
	 * Opens the rule of this page as library item and asks to roll it out to
	 * the projects you picked.
	 */
	public function rollOutPicked(): void
	{
		if ($this->library !== null && $this->rule !== '') {
			$this->askRollOut($this->pickedProjects, $this->library . ':' . $this->rule);
		}
	}

	public function startAdding(): void
	{
		$this->addingRules = true;
		$this->followPicks = [];
		$this->filePicks = [];
		$this->otherSearch = '';
	}

	public function toggleFollowPick(int $library): void
	{
		$this->followPicks = in_array($library, $this->followPicks, true)
			? array_values(array_diff($this->followPicks, [$library]))
			: [...$this->followPicks, $library];
	}

	public function toggleFilePick(string $key): void
	{
		$this->filePicks = in_array($key, $this->filePicks, true)
			? array_values(array_diff($this->filePicks, [$key]))
			: [...$this->filePicks, $key];
	}

	/**
	 * Lets the project follow the picked libraries and puts their rules down,
	 * and copies the loose files over. Nothing is committed.
	 */
	public function addRules(): void
	{
		$written = 0;

		foreach (GetLibrariesAction::execute() as $libraryEntity) {
			if (! in_array($libraryEntity->id, $this->followPicks, true)) {
				continue;
			}

			FollowLibraryAction::execute($libraryEntity->id, $this->project, true);

			foreach ($libraryEntity->rules() as $itemEntity) {
				$written += RollOutAction::execute($itemEntity, [$this->project]);
			}
		}

		$files = collect(GetRuleFilesAction::execute());

		foreach ($this->filePicks as $key) {
			[$project, $path] = explode('|', $key, 2) + [1 => ''];
			$ruleFileEntity = $files->first(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->project === $project && $ruleFileEntity->path === $path);

			if ($ruleFileEntity !== null && CopyRuleFileToProjectAction::execute($ruleFileEntity->ruleFileId, $this->project)) {
				$written++;
			}
		}

		$this->addingRules = false;
		$this->libraryDone = trans_choice(':count bestand neergezet. Committen doe je zelf.|:count bestanden neergezet. Committen doe je zelf.', $written, ['count' => $written]);
	}

	public function startLanding(): void
	{
		[$landing, $folder] = $this->landingOf($this->project);

		$this->landingChoice = $landing->value;
		$this->landingFolder = (string) $folder;
		$this->choosingLanding = true;
	}

	public function saveLanding(): void
	{
		$landing = LandingEnum::tryFrom($this->landingChoice);

		if ($landing !== null && $this->project !== '') {
			SetLandingAction::execute($this->project, $landing, $this->landingFolder);
		}

		$this->choosingLanding = false;
	}

	public function startRule(): void
	{
		$this->creatingRule = true;
		$this->addingRules = false;
		$this->newRuleName = '';
		$this->newRuleStart = 'empty';
		$this->newRuleWhere = $this->library !== null ? 'library:' . $this->library : ($this->global ? 'global' : 'project');
		$this->resetValidation();
	}

	/**
	 * Lays down a new rule: as version 1 in a library, as a file in the
	 * project, or in ~/.claude for yourself.
	 */
	public function createRule(): void
	{
		$this->validate(['newRuleName' => ['required', 'regex:/^[A-Za-z0-9_.\/-]+$/']], [
			'newRuleName.required' => __('Geef de regel een naam.'),
			'newRuleName.regex' => __('Een naam bestaat uit letters, cijfers, streepjes en punten.'),
		]);

		$name = str_ends_with($this->newRuleName, '.md') ? $this->newRuleName : $this->newRuleName . '.md';
		$contents = $this->startContents($name);

		if (str_starts_with($this->newRuleWhere, 'library:')) {
			SaveVersionAction::execute((int) substr($this->newRuleWhere, 8), LibraryItemKindEnum::RULE, $name, VersionSourceEnum::EDIT, $contents, __('Eerste versie'));
		} elseif ($this->newRuleWhere === 'global') {
			CreateRuleFileAction::execute(GetGlobalRulesAction::folder() . '/rules/' . $name, $contents);
		} else {
			$projectPath = $this->projectPath($this->project);
			[$landing, $folder] = $this->landingOf($this->project);

			if ($projectPath !== null) {
				CreateRuleFileAction::execute($projectPath . '/' . ($landing === LandingEnum::AGENTS ? '.claude/rules/' . $name : $landing->pathFor($name, $folder)), $contents);
			}
		}

		$this->creatingRule = false;
		$this->libraryDone = __(':name is aangemaakt. De inhoud schrijf je daarna in je editor.', ['name' => $name]);
	}

	protected function libraryKind(): LibraryItemKindEnum
	{
		return LibraryItemKindEnum::RULE;
	}

	private function resetPage(): void
	{
		$this->project = '';
		$this->library = null;
		$this->rule = '';
		$this->global = false;
		$this->file = '';
		$this->pickedProjects = [];
		$this->libraryDone = null;
		$this->libraryItem = null;
	}

	/**
	 * The page on the right, ready to show.
	 *
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @param  Collection<int, RuleFileEntity>  $ruleFiles
	 * @param  Collection<int, array<string, mixed>>  $projectSummaries
	 * @return array<string, mixed>
	 */
	private function pageData(Collection $libraryEntities, Collection $ruleFiles, Collection $projectSummaries): array
	{
		if ($this->global) {
			return $this->globalPage();
		}

		$libraryEntity = $this->library === null ? null : $libraryEntities->first(fn (LibraryEntity $item) => $item->id === $this->library);

		if ($libraryEntity !== null && $this->rule !== '') {
			$itemEntity = $libraryEntity->item(LibraryItemKindEnum::RULE, $this->rule);

			return $itemEntity === null ? ['kind' => 'none'] : $this->rulePage($libraryEntity, $itemEntity, $libraryEntities);
		}

		if ($libraryEntity !== null) {
			return $this->libraryPage($libraryEntity, $projectSummaries);
		}

		return $this->project === '' ? ['kind' => 'none'] : $this->projectPage($libraryEntities, $ruleFiles);
	}

	/**
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @param  Collection<int, RuleFileEntity>  $ruleFiles
	 * @return array<string, mixed>
	 */
	private function projectPage(Collection $libraryEntities, Collection $ruleFiles): array
	{
		$followed = $libraryEntities->filter(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy($this->project))->values();
		[$landing, $folder, $isChosen] = $this->landingOf($this->project);
		$projectPath = (string) $this->projectPath($this->project);

		$groups = $followed->map(function (LibraryEntity $libraryEntity): array {
			$standings = collect(GetStandingsAction::execute($libraryEntity, [$this->project]))
				->filter(fn (StandingObject $standingObject) => $standingObject->item->kind === LibraryItemKindEnum::RULE)
				->sortBy(fn (StandingObject $standingObject) => [$standingObject->standing === StandingEnum::EQUAL, $standingObject->item->name])
				->values();

			return [
				'library' => $libraryEntity,
				'standings' => $standings,
				'present' => $standings->reject(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::MISSING)->count(),
				'deviating' => $standings->filter(fn (StandingObject $standingObject) => $standingObject->standing->deviates())->count(),
			];
		});

		$libraryPaths = $groups->flatMap(fn (array $group) => $group['standings']->pluck('path'))->all();
		$files = $ruleFiles->filter(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->project === $this->project);
		$isYours = fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->group === RuleGroupEnum::MEMORY || str_ends_with($ruleFileEntity->path, 'CLAUDE.local.md');
		$permissions = collect(GetPermissionsAction::execute());

		return [
			'kind' => 'project',
			'followed' => $followed,
			'unfollowed' => $libraryEntities->reject(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy($this->project))->values(),
			'landing' => $landing,
			'landingFolder' => $folder,
			'landingLabel' => $landing === LandingEnum::CUSTOM ? (string) $folder : $landing->label(),
			'landingChosen' => $isChosen,
			'landingExists' => $landing === LandingEnum::AGENTS ? is_file($projectPath . '/AGENTS.md') : is_dir($projectPath . '/' . dirname($landing->pathFor('x.md', $folder))),
			'groups' => $groups,
			'own' => $files->reject(fn (RuleFileEntity $ruleFileEntity) => in_array($ruleFileEntity->path, $libraryPaths, true) || $isYours($ruleFileEntity))->values(),
			'yours' => $files->filter($isYours)->values(),
			'readCount' => $files->reject(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->group === RuleGroupEnum::MEMORY)->count(),
			'projectPermissions' => $permissions->filter(fn (PermissionRuleObject $rule) => $rule->project === $this->project)->values(),
			'globalPermissions' => $permissions->filter(fn (PermissionRuleObject $rule) => $rule->project === null)->values(),
			'favoritePaths' => collect(resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::RULE))
				->filter(fn (array $favorite) => $favorite['project'] === $this->project)
				->pluck('path')
				->all(),
		];
	}

	/**
	 * @param  Collection<int, array<string, mixed>>  $projectSummaries
	 * @return array<string, mixed>
	 */
	private function libraryPage(LibraryEntity $libraryEntity, Collection $projectSummaries): array
	{
		$standings = collect(GetStandingsAction::execute($libraryEntity));

		$rows = collect($libraryEntity->rules())->map(function (LibraryItemEntity $itemEntity) use ($standings, $libraryEntity): array {
			$mine = $standings->filter(fn (StandingObject $standingObject) => $standingObject->item->id === $itemEntity->id);

			return [
				'item' => $itemEntity,
				'latest' => $itemEntity->latest(),
				'followers' => count($libraryEntity->followers),
				'equal' => $mine->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::EQUAL)->count(),
				'behind' => $mine->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::BEHIND)->count(),
				'modified' => $mine->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::MODIFIED)->count(),
				'missing' => $mine->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::MISSING)->pluck('project')->all(),
			];
		});

		$withoutLibrary = $projectSummaries->filter(fn (array $row) => $row['libraries'] === 0)->pluck('name')->values();

		return [
			'kind' => 'library',
			'library' => $libraryEntity,
			'rows' => $rows,
			'standingCases' => StandingEnum::cases(),
			'withoutLibrary' => $withoutLibrary,
		];
	}

	/**
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @return array<string, mixed>
	 */
	private function rulePage(LibraryEntity $libraryEntity, LibraryItemEntity $itemEntity, Collection $libraryEntities): array
	{
		$standings = $this->itemStandings($libraryEntity, $itemEntity);
		$disk = resolve(LibraryDiskRepositoryContract::class);
		$others = [];
		$notFollowing = [];

		foreach (GetProjectsAction::execute() as $projectEntity) {
			if ($libraryEntity->isFollowedBy($projectEntity->name)) {
				continue;
			}

			$contents = $disk->projectFiles($projectEntity->path, LibraryItemKindEnum::RULE)[$itemEntity->name] ?? null;
			$otherLibrary = $libraryEntities->first(fn (LibraryEntity $item) => $item->id !== $libraryEntity->id && $item->isFollowedBy($projectEntity->name));

			if ($contents !== null && $otherLibrary !== null) {
				$others[] = ['project' => $projectEntity->name, 'library' => $otherLibrary->name, 'lines' => substr_count($contents, "\n") + 1];

				continue;
			}

			[$landing, $folder] = $this->landingOf($projectEntity->name, $projectEntity->path);

			$notFollowing[] = ['project' => $projectEntity->name, 'landing' => $landing, 'path' => $landing->pathFor($itemEntity->name, $folder)];
		}

		return [
			'kind' => 'rule',
			'library' => $libraryEntity,
			'item' => $itemEntity,
			'latest' => $itemEntity->latest(),
			...$standings,
			'others' => $others,
			'notFollowing' => $notFollowing,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function globalPage(): array
	{
		$global = GetGlobalRulesAction::execute();
		$permissions = collect(GetPermissionsAction::execute())
			->filter(fn (PermissionRuleObject $rule) => $rule->project === null)
			->values();

		return [
			'kind' => 'global',
			'folder' => $global['folder'],
			'files' => $global['files'],
			'defaultMode' => $global['defaultMode'],
			'allowed' => $permissions->filter(fn (PermissionRuleObject $rule) => $rule->kind === 'allow')->values(),
			'denied' => $permissions->filter(fn (PermissionRuleObject $rule) => $rule->kind === 'deny')->values(),
			'asked' => $permissions->filter(fn (PermissionRuleObject $rule) => $rule->kind === 'ask')->values(),
			'projectCount' => GetProjectsAction::execute()->count(),
		];
	}

	/**
	 * Per project how it stands against the libraries it follows, for the list
	 * on the left.
	 *
	 * @param  Collection<int, string>  $projectNames
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @param  Collection<int, RuleFileEntity>  $ruleFiles
	 * @return Collection<int, array{name: string, tone: string, note: string, noteTone: string, deviates: bool, libraries: int}>
	 */
	private function projectSummaries(Collection $projectNames, Collection $libraryEntities, Collection $ruleFiles): Collection
	{
		$standings = $libraryEntities
			->flatMap(fn (LibraryEntity $libraryEntity) => GetStandingsAction::execute($libraryEntity))
			->filter(fn (StandingObject $standingObject) => $standingObject->item->kind === LibraryItemKindEnum::RULE)
			->groupBy('project');

		return $projectNames->map(function (string $name) use ($standings, $libraryEntities, $ruleFiles): array {
			$mine = collect($standings->get($name, []));
			$followed = $libraryEntities->filter(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy($name));
			$behind = $mine->filter(fn (StandingObject $standingObject) => $standingObject->standing === StandingEnum::BEHIND)->count();
			$modified = $mine->filter(fn (StandingObject $standingObject) => in_array($standingObject->standing, [StandingEnum::MODIFIED, StandingEnum::MISSING], true))->count();
			$hasFiles = $ruleFiles->contains(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->project === $name && $ruleFileEntity->group !== RuleGroupEnum::MEMORY);

			[$note, $noteTone] = match (true) {
				$followed->isEmpty() => [$hasFiles ? __('geen bibliotheek') : __('geen regels'), ''],
				$behind > 0 && $modified > 0 => [trans_choice(':count wijkt af|:count wijken af', $behind + $modified, ['count' => $behind + $modified]), 'amber'],
				$behind > 0 => [__(':count achter', ['count' => $behind]), 'amber'],
				$modified > 0 => [__(':count aangepast', ['count' => $modified]), 'blue'],
				default => [$followed->count() === 1 && $followed->first()->id !== $libraryEntities->first()?->id ? __('volgt :library', ['library' => $followed->first()->name]) : __('gelijk'), ''],
			};

			return [
				'name' => $name,
				'tone' => $followed->isEmpty() ? 'faint' : ($behind > 0 ? 'amber' : ($modified > 0 ? 'blue' : 'green')),
				'note' => $note,
				'noteTone' => $noteTone,
				'deviates' => $behind + $modified > 0,
				'libraries' => $followed->count(),
			];
		})->sortBy(fn (array $row) => [! $row['deviates'], $row['name']])->values();
	}

	/**
	 * Everything the drawer of one rule file in the project shows.
	 *
	 * @param  Collection<int, RuleFileEntity>  $ruleFiles
	 * @return array<string, mixed>|null
	 */
	private function fileData(Collection $ruleFiles): ?array
	{
		$ruleFileEntity = $ruleFiles->first(fn (RuleFileEntity $item) => $item->project === $this->project && $item->path === $this->file);
		$standingObject = $this->fileStanding();

		if ($ruleFileEntity === null && $standingObject === null) {
			return null;
		}

		$contents = $ruleFileEntity === null ? null : GetRuleFileContentsAction::execute($ruleFileEntity->ruleFileId);
		$others = $ruleFiles
			->filter(fn (RuleFileEntity $item) => $item->project !== $this->project && basename($item->path) === basename($this->file))
			->values();

		$choices = [];

		foreach (array_reverse($standingObject?->item->versions ?? []) as $versionEntity) {
			$choices['v:' . $versionEntity->number] = $standingObject->item->library . ' ' . $versionEntity->label() . ($versionEntity->number === $standingObject->item->latest()?->number ? ', ' . __('de nieuwste') : '');
		}

		foreach ($others as $other) {
			$choices['p:' . $other->project] = $other->project;
		}

		$target = $this->compareWith !== '' ? $this->compareWith : (string) array_key_first($choices);

		return [
			'file' => $ruleFileEntity,
			'path' => $this->file,
			'contents' => $contents,
			'standing' => $standingObject,
			'others' => $others,
			'choices' => $choices,
			'target' => $target,
			'diff' => $this->fileTab === 'Vergelijken' && $contents !== null && $target !== ''
				? DiffTextsAction::execute($contents, $this->compareContents($target, $standingObject, $others))
				: null,
			'isFavorite' => collect(resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::RULE))
				->contains(fn (array $favorite) => $favorite['project'] === $this->project && $favorite['path'] === $this->file),
		];
	}

	/**
	 * @param  Collection<int, RuleFileEntity>  $others
	 */
	private function compareContents(string $target, ?StandingObject $standingObject, Collection $others): string
	{
		if (str_starts_with($target, 'v:')) {
			return (string) $standingObject?->item->versionNumbered((int) substr($target, 2))?->contents;
		}

		$other = $others->first(fn (RuleFileEntity $item) => $item->project === substr($target, 2));

		return $other === null ? '' : (string) GetRuleFileContentsAction::execute($other->ruleFileId);
	}

	/**
	 * The library item behind the open file of the project, with how it stands.
	 */
	private function fileStanding(): ?StandingObject
	{
		if ($this->project === '' || $this->file === '') {
			return null;
		}

		return collect(GetLibrariesAction::execute())
			->filter(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy($this->project))
			->flatMap(fn (LibraryEntity $libraryEntity) => GetStandingsAction::execute($libraryEntity, [$this->project]))
			->first(fn (StandingObject $standingObject) => $standingObject->path === $this->file || ($standingObject->path === 'AGENTS.md' && $this->file === 'AGENTS.md'));
	}

	/**
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @param  Collection<int, RuleFileEntity>  $ruleFiles
	 * @return array<string, mixed>
	 */
	private function addingData(Collection $libraryEntities, Collection $ruleFiles): array
	{
		[$landing, $folder] = $this->landingOf($this->project);
		$favorites = collect(resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::RULE))
			->reject(fn (array $favorite) => $favorite['project'] === $this->project);

		return [
			'libraries' => $libraryEntities->reject(fn (LibraryEntity $libraryEntity) => $libraryEntity->isFollowedBy($this->project))->values(),
			'favorites' => $favorites->map(fn (array $favorite) => [
				...$favorite,
				'key' => $favorite['project'] . '|' . $favorite['path'],
				'lines' => $ruleFiles->first(fn (RuleFileEntity $item) => $item->project === $favorite['project'] && $item->path === $favorite['path'])?->lines,
			])->values(),
			'found' => $this->otherSearch === ''
				? collect()
				: $ruleFiles
					->filter(fn (RuleFileEntity $item) => $item->project !== $this->project
						&& $item->group !== RuleGroupEnum::MEMORY
						&& str_contains(strtolower($item->project . '/' . $item->path), strtolower($this->otherSearch)))
					->take(8)
					->map(fn (RuleFileEntity $item) => ['project' => $item->project, 'path' => $item->path, 'key' => $item->project . '|' . $item->path, 'lines' => $item->lines])
					->values(),
			'fileCount' => $libraryEntities
				->filter(fn (LibraryEntity $libraryEntity) => in_array($libraryEntity->id, $this->followPicks, true))
				->sum(fn (LibraryEntity $libraryEntity) => count($libraryEntity->rules())) + count($this->filePicks),
			'landing' => $landing,
			'landingFolder' => $folder,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function landingData(): array
	{
		$projectPath = (string) $this->projectPath($this->project);

		return [
			'cases' => LandingEnum::cases(),
			'existing' => resolve(LibraryDiskRepositoryContract::class)->landingsIn($projectPath),
		];
	}

	/**
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @return array<string, mixed>
	 */
	private function newRuleData(Collection $libraryEntities): array
	{
		$name = str_ends_with($this->newRuleName, '.md') || $this->newRuleName === '' ? $this->newRuleName : $this->newRuleName . '.md';

		return [
			'libraries' => $libraryEntities,
			'favorites' => resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::RULE),
			'fileName' => $name,
			'libraryName' => str_starts_with($this->newRuleWhere, 'library:')
				? $libraryEntities->first(fn (LibraryEntity $libraryEntity) => $libraryEntity->id === (int) substr($this->newRuleWhere, 8))?->name
				: null,
		];
	}

	/**
	 * What a new rule starts with: a heading, or a copy of a favourite.
	 */
	private function startContents(string $name): string
	{
		if (str_starts_with($this->newRuleStart, 'copy:')) {
			[$project, $path] = explode('|', substr($this->newRuleStart, 5), 2) + [1 => ''];
			$ruleFileEntity = collect(GetRuleFilesAction::execute())
				->first(fn (RuleFileEntity $item) => $item->project === $project && $item->path === $path);

			if ($ruleFileEntity !== null) {
				return (string) GetRuleFileContentsAction::execute($ruleFileEntity->ruleFileId);
			}
		}

		return '# ' . ucfirst(str_replace(['-', '_'], ' ', pathinfo($name, PATHINFO_FILENAME))) . "\n";
	}

	/**
	 * @return array{0: LandingEnum, 1: ?string, 2: bool}
	 */
	private function landingOf(string $project, ?string $projectPath = null): array
	{
		return GetLandingAction::execute($project, $projectPath ?? (string) $this->projectPath($project));
	}

	private function projectPath(string $project): ?string
	{
		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $project)
			?->path;
	}

	public function render(): View
	{
		$libraryEntities = collect(GetLibrariesAction::execute());
		$ruleFiles = collect(GetRuleFilesAction::execute());
		$projectNames = GetProjectsAction::execute()->pluck('name')->sort()->values();
		$projectSummaries = $this->projectSummaries($projectNames, $libraryEntities, $ruleFiles);

		return view('livewire.rules', [
			'libraries' => $libraryEntities,
			'favorites' => resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::RULE),
			'projectRows' => $projectSummaries
				->filter(fn (array $row) => $this->search === '' || str_contains(strtolower($row['name']), strtolower($this->search)))
				->filter(fn (array $row) => ! $this->onlyDeviating || $row['deviates'])
				->when(! $this->allProjects && $this->search === '', fn (Collection $rows) => $rows->take(self::PROJECTS_SHOWN))
				->values(),
			'hiddenProjects' => $this->allProjects || $this->search !== '' ? 0 : max(0, $projectSummaries->count() - self::PROJECTS_SHOWN),
			'projectTotal' => $projectSummaries->count(),
			'deviatingCount' => $projectSummaries->filter(fn (array $row) => $row['deviates'])->count(),
			'page' => $this->pageData($libraryEntities, $ruleFiles, $projectSummaries),
			'fileDrawer' => $this->file === '' || $this->project === '' ? null : $this->fileData($ruleFiles),
			'itemDrawer' => $this->libraryItemData(),
			'newLibrary' => $this->newLibraryData(),
			'adding' => $this->addingRules ? $this->addingData($libraryEntities, $ruleFiles) : null,
			'landings' => $this->choosingLanding ? $this->landingData() : null,
			'newRule' => $this->creatingRule ? $this->newRuleData($libraryEntities) : null,
		]);
	}
}
