<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithLibraries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Library\Actions\GetLibrariesAction;
use Src\Library\Actions\SaveVersionAction;
use Src\Library\Actions\ToggleFavoriteAction;
use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryVersionEntity;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Skill\Actions\CopySkillToProjectAction;
use Src\Skill\Actions\CountSkillFilesInPlaceAction;
use Src\Skill\Actions\CreateSkillAction;
use Src\Skill\Actions\GetSkillContentsAction;
use Src\Skill\Actions\GetSkillsAction;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Objects\SkillEntity;

class Skills extends Component
{
	use WithLibraries;

	/**
	 * A finished round of fetching brings the page up to date.
	 */
	protected $listeners = ['fetched' => '$refresh'];

	/**
	 * What the page on the right shows: a project, your global skills, the
	 * plugins, a library, or one skill across projects.
	 */
	#[Url(except: '')]
	public string $project = '';

	#[Url(except: '')]
	public string $section = '';

	#[Url(except: null)]
	public ?int $library = null;

	#[Url(except: '')]
	public string $skill = '';

	public string $search = '';

	public bool $onlyWithSkills = true;

	public ?string $openSkill = null;

	public bool $creating = false;

	public string $newName = '';

	public string $newDescription = '';

	public string $newWhere = '';

	public bool $copying = false;

	public string $copyTo = '';

	public string $versionFrom = '';

	public ?int $versionLibrary = null;

	public bool $allProjectSkills = false;

	public function mount(): void
	{
		if ($this->project === '' && $this->section === '' && $this->library === null && $this->skill === '') {
			$this->project = collect(GetSkillsAction::execute())
				->filter(fn (SkillEntity $skillEntity) => $skillEntity->project !== null)
				->countBy('project')
				->sortDesc()
				->keys()
				->first() ?? '';
		}
	}

	public function showProject(string $project): void
	{
		$this->resetPage();
		$this->project = $project;
	}

	public function showSection(string $section): void
	{
		$this->resetPage();
		$this->section = $section;
	}

	public function showLibrary(int $library): void
	{
		$this->resetPage();
		$this->library = $library;
	}

	public function showSkill(string $skill): void
	{
		$this->resetPage();
		$this->skill = $skill;
		$this->versionLibrary = collect(GetLibrariesAction::execute())->first()?->id;
	}

	public function open(string $key): void
	{
		$this->openSkill = $key;
		$this->copying = false;
	}

	public function closeDrawer(): void
	{
		$this->openSkill = null;
	}

	public function toggleFavorite(string $project, string $name): void
	{
		ToggleFavoriteAction::execute(LibraryItemKindEnum::SKILL, $project, $name);
	}

	public function startCopy(): void
	{
		$this->copying = true;
		$this->copyTo = '';
	}

	/**
	 * Puts the whole skill folder in the chosen project. Nothing is ever taken
	 * away there.
	 */
	public function copy(): void
	{
		$skillEntity = $this->openSkillEntity(collect(GetSkillsAction::execute()));

		if ($skillEntity !== null && filled($this->copyTo)) {
			$copied = CopySkillToProjectAction::execute($skillEntity->skillId, $this->copyTo);
			$this->libraryDone = $copied ? __('Overgenomen in :project.', ['project' => $this->copyTo]) : null;
		}

		$this->copying = false;
	}

	/**
	 * Takes the open skill of a project into a library as a new version.
	 */
	public function intoLibrary(int $library): void
	{
		$skillEntity = $this->openSkillEntity(collect(GetSkillsAction::execute()));

		if ($skillEntity?->project !== null) {
			SaveVersionAction::execute($library, LibraryItemKindEnum::SKILL, $skillEntity->name, VersionSourceEnum::PROJECT, null, __('Uit :project', ['project' => $skillEntity->project]), $skillEntity->project);
			$this->libraryDone = __(':name staat in de bibliotheek.', ['name' => $skillEntity->name]);
		}
	}

	/**
	 * Saves the picked version of the skill of this page as its library
	 * version.
	 */
	public function saveChosenVersion(): void
	{
		if ($this->versionFrom !== '' && $this->versionLibrary !== null) {
			SaveVersionAction::execute($this->versionLibrary, LibraryItemKindEnum::SKILL, $this->skill, VersionSourceEnum::PROJECT, null, __('Uit :project', ['project' => $this->versionFrom]), $this->versionFrom);
			$this->libraryDone = __('De versie van :project staat in de bibliotheek.', ['project' => $this->versionFrom]);
		}
	}

	public function startCreating(): void
	{
		$this->creating = true;
		$this->newName = '';
		$this->newDescription = '';
		$this->newWhere = $this->project !== '' ? $this->project : SkillScopeEnum::GLOBALLY->value;
		$this->resetValidation();
	}

	public function create(): void
	{
		$this->validate([
			'newName' => ['required', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
			'newDescription' => ['required', 'min:20'],
			'newWhere' => ['required'],
		], [
			'newName.regex' => __('Een naam bestaat uit kleine letters, cijfers en streepjes.'),
			'newDescription.min' => __('Beschrijf in een zin wanneer Claude deze skill moet pakken.'),
		]);

		if (str_starts_with($this->newWhere, 'library:')) {
			SaveVersionAction::execute(
				(int) substr($this->newWhere, 8),
				LibraryItemKindEnum::SKILL,
				$this->newName,
				VersionSourceEnum::EDIT,
				(string) json_encode(['SKILL.md' => "---\nname: " . $this->newName . "\ndescription: " . $this->newDescription . "\n---\n\n# " . $this->newName . "\n"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
				__('Eerste versie'),
			);
			$this->creating = false;

			return;
		}

		if (CreateSkillAction::execute($this->newName, $this->newDescription, $this->newWhere) === null) {
			$this->addError('newName', __('Er bestaat hier al een skill met deze naam.'));

			return;
		}

		$this->creating = false;
	}

	protected function libraryKind(): LibraryItemKindEnum
	{
		return LibraryItemKindEnum::SKILL;
	}

	private function resetPage(): void
	{
		$this->project = '';
		$this->section = '';
		$this->library = null;
		$this->skill = '';
		$this->openSkill = null;
		$this->libraryDone = null;
		$this->libraryItem = null;
		$this->versionFrom = '';
	}

	/**
	 * @param  Collection<int, SkillEntity>  $all
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @param  array<string, array<string, string>>  $versions
	 * @return array<string, mixed>
	 */
	private function pageData(Collection $all, Collection $libraryEntities, array $versions): array
	{
		if ($this->skill !== '') {
			return $this->skillPage($versions[$this->skill] ?? [], $libraryEntities);
		}

		if ($this->library !== null) {
			$libraryEntity = $libraryEntities->first(fn (LibraryEntity $item) => $item->id === $this->library);

			return $libraryEntity === null ? ['kind' => 'none'] : ['kind' => 'library', 'library' => $libraryEntity];
		}

		if ($this->section === 'global' || $this->section === 'plugins') {
			$scope = $this->section === 'global' ? SkillScopeEnum::GLOBALLY : SkillScopeEnum::PLUGIN;

			return [
				'kind' => $this->section,
				'skills' => $all->filter(fn (SkillEntity $item) => $item->scope === $scope)->sortBy('name')->values(),
			];
		}

		if ($this->project === '') {
			return ['kind' => 'none'];
		}

		$own = $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::PROJECT && $item->project === $this->project)->sortBy('name')->values();
		$rows = $own->map(function (SkillEntity $skillEntity) use ($versions): array {
			$elsewhere = collect($versions[$skillEntity->name] ?? [])->except($this->project);
			$variants = collect($versions[$skillEntity->name] ?? [])->unique()->count();

			return [
				'skill' => $skillEntity,
				'note' => $elsewhere->isEmpty()
					? __('alleen hier')
					: trans_choice('ook in :count project|ook in :count projecten', $elsewhere->count(), ['count' => $elsewhere->count()])
						. ' · ' . ($variants > 1 ? trans_choice(':count versie|:count versies', $variants, ['count' => $variants]) : __('dezelfde versie')),
				'differs' => $variants > 1,
			];
		});

		return [
			'kind' => 'project',
			'rows' => $this->allProjectSkills ? $rows : $rows->take(8),
			'hiddenRows' => $this->allProjectSkills ? 0 : max(0, $rows->count() - 8),
			'ownCount' => $own->count(),
			'differCount' => $rows->filter(fn (array $row) => $row['differs'])->count(),
			'firstDiffering' => $rows->first(fn (array $row) => $row['differs'])['skill']->name ?? null,
			'libraryHasSkills' => $libraryEntities->contains(fn (LibraryEntity $libraryEntity) => $libraryEntity->skills() !== []),
			'global' => $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::GLOBALLY)->values(),
			'plugins' => $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::PLUGIN)->values(),
		];
	}

	/**
	 * One skill across the projects that hold it, with how much each version
	 * differs, to choose the one for the library.
	 *
	 * @param  array<string, string>  $byProject  project to the hash of its version
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @return array<string, mixed>
	 */
	private function skillPage(array $byProject, Collection $libraryEntities): array
	{
		$disk = resolve(LibraryDiskRepositoryContract::class);
		$projectPaths = GetProjectsAction::execute()->keyBy('name');

		$rows = collect($byProject)->map(function (string $hash, string $project) use ($disk, $projectPaths): array {
			$projectEntity = $projectPaths->get($project);
			$files = $projectEntity instanceof ProjectEntity ? $disk->projectFiles($projectEntity->path, LibraryItemKindEnum::SKILL) : [];
			$contents = json_decode($files[$this->skill] ?? '{}', true);
			$lines = substr_count((string) ($contents['SKILL.md'] ?? ''), "\n") + 1;

			return ['project' => $project, 'hash' => $hash, 'lines' => $lines, 'files' => is_array($contents) ? count($contents) : 0];
		})->sortByDesc('lines')->values();

		$inLibrary = $libraryEntities->first(fn (LibraryEntity $libraryEntity) => $libraryEntity->item(LibraryItemKindEnum::SKILL, $this->skill) !== null);

		return [
			'kind' => 'skill',
			'rows' => $rows,
			'variants' => $rows->unique('hash')->count(),
			'inLibrary' => $inLibrary,
		];
	}

	/**
	 * Per skill name, per project, a hash of the whole skill folder, so the
	 * versions can be told apart.
	 *
	 * @param  Collection<int, SkillEntity>  $all
	 * @return array<string, array<string, string>>
	 */
	private function versionsAcrossProjects(Collection $all): array
	{
		$disk = resolve(LibraryDiskRepositoryContract::class);
		$versions = [];

		foreach (GetProjectsAction::execute() as $projectEntity) {
			if (! $all->contains(fn (SkillEntity $skillEntity) => $skillEntity->project === $projectEntity->name)) {
				continue;
			}

			foreach ($disk->projectFiles($projectEntity->path, LibraryItemKindEnum::SKILL) as $name => $contents) {
				$versions[$name][$projectEntity->name] = LibraryVersionEntity::hashOf($contents);
			}
		}

		return $versions;
	}

	/**
	 * @param  Collection<int, LibraryEntity>  $libraryEntities
	 * @return array<string, string>
	 */
	private function newSkillPlaces(Collection $libraryEntities): array
	{
		$places = [];

		foreach ($libraryEntities as $libraryEntity) {
			$places['library:' . $libraryEntity->id] = __('In de bibliotheek (:library)', ['library' => $libraryEntity->name]);
		}

		$places[SkillScopeEnum::GLOBALLY->value] = __('Globaal, geldt in elk project');

		foreach (GetProjectsAction::execute()->pluck('name')->sort() as $name) {
			$places[$name] = $name;
		}

		return $places;
	}

	/**
	 * @param  Collection<int, SkillEntity>  $all
	 */
	private function openSkillEntity(Collection $all): ?SkillEntity
	{
		if ($this->openSkill === null) {
			return null;
		}

		[$project, $name] = explode('|', $this->openSkill, 2) + [1 => ''];

		return $all->first(fn (SkillEntity $skillEntity) => $skillEntity->name === $name && (string) $skillEntity->project === $project);
	}

	/**
	 * What already stands under this skill name in the chosen project; that goes
	 * under.
	 */
	private function copyWarning(SkillEntity $skillEntity): ?string
	{
		if (blank($this->copyTo)) {
			return null;
		}

		$filesInPlace = CountSkillFilesInPlaceAction::execute($skillEntity->skillId, $this->copyTo);

		return $filesInPlace === 0 ? null : trans_choice(
			'Daar staat al een skill met deze naam; :count bestand wordt overschreven.|Daar staat al een skill met deze naam; :count bestanden worden overschreven.',
			$filesInPlace,
			['count' => $filesInPlace],
		);
	}

	public function render(): View
	{
		$all = collect(GetSkillsAction::execute());
		$libraryEntities = collect(GetLibrariesAction::execute());
		$skillEntity = $this->openSkillEntity($all);
		$versions = $this->versionsAcrossProjects($all);
		$perProject = $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::PROJECT)->countBy('project');

		return view('livewire.skills', [
			'libraries' => $libraryEntities,
			'globalCount' => $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::GLOBALLY)->count(),
			'pluginCount' => $all->filter(fn (SkillEntity $item) => $item->scope === SkillScopeEnum::PLUGIN)->count(),
			'favorites' => resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::SKILL),
			'projectRows' => GetProjectsAction::execute()
				->pluck('name')
				->sort()
				->map(fn (string $name) => ['name' => $name, 'count' => (int) ($perProject[$name] ?? 0)])
				->filter(fn (array $row) => ! $this->onlyWithSkills || $row['count'] > 0)
				->filter(fn (array $row) => $this->search === '' || str_contains(strtolower($row['name']), strtolower($this->search)))
				->values(),
			'withoutSkills' => GetProjectsAction::execute()->count() - $perProject->count(),
			'withSkills' => $perProject->count(),
			'page' => $this->pageData($all, $libraryEntities, $versions),
			'skillDrawer' => $skillEntity === null ? null : [
				'skill' => $skillEntity,
				'body' => resolve(RenderMarkdown::class)->toHtml((string) GetSkillContentsAction::execute($skillEntity->skillId)),
				'isFavorite' => collect(resolve(LibraryRepositoryContract::class)->getFavorites(LibraryItemKindEnum::SKILL))
					->contains(fn (array $favorite) => $favorite['project'] === $skillEntity->project && $favorite['path'] === $skillEntity->name),
				'projectsToCopyTo' => GetProjectsAction::execute()->pluck('name')->reject(fn (string $name) => $name === $skillEntity->project)->sort()->values(),
				'copyWarning' => $this->copyWarning($skillEntity),
			],
			'itemDrawer' => $this->libraryItemData(),
			'newLibrary' => $this->newLibraryData(),
			'newSkillPlaces' => $this->creating ? $this->newSkillPlaces($libraryEntities) : [],
		]);
	}
}
