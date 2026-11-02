<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Review\Actions\CheckWorkflowAction;
use Src\Review\Actions\CreateLensAction;
use Src\Review\Actions\CustomizeLensAction;
use Src\Review\Actions\GetAgentContextBlocksAction;
use Src\Review\Actions\GetLensesAction;
use Src\Review\Actions\GetWorkflowSettingAction;
use Src\Review\Actions\SaveAgentContextBlocksAction;
use Src\Review\Actions\SaveWorkflowSettingAction;
use Src\Review\Actions\ToggleLensAction;
use Src\Review\Actions\UpdateStandardLensAction;
use Src\Review\Enums\AfterRoundsEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\LensId;
use Src\Setting\Contracts\SettingRepositoryContract;

class Workflows extends Component
{
	use WithContext;

	/**
	 * The thresholds you can pick from.
	 */
	private const ROUND_CHOICES = [2, 3, 4, 5];

	/**
	 * A finished round of fetching brings the page up to date.
	 */
	protected $listeners = ['fetched' => '$refresh'];

	#[Url(except: '')]
	public string $project = '';

	/**
	 * What the page shows of the project: its workflow, or the context its agents and lenses get.
	 */
	#[Url(except: 'workflow')]
	public string $view = 'workflow';

	public string $search = '';

	public bool $onlyAdjust = false;

	/** @var array<string, string> */
	public array $context = [];

	public string $projectContext = '';

	public ?string $saved = null;

	public ?int $openLens = null;

	/** @var array<int, string> */
	public array $lensPaths = [];

	/** @var array<int, string> */
	public array $lensRules = [];

	public string $lensCommand = '';

	public string $lensDescription = '';

	public string $newPath = '';

	public bool $creatingLens = false;

	public string $lensName = '';

	public bool $alsoInStandard = false;

	public bool $editingThreshold = false;

	public function mount(): void
	{
		$this->context = GetAgentContextBlocksAction::execute();
		$this->projectContext = (string) GetWorkflowSettingAction::execute($this->project ?: null)->context;
	}

	public function selectProject(string $project): void
	{
		$this->project = $project;
		$this->projectContext = (string) GetWorkflowSettingAction::execute($project ?: null)->context;
		$this->openLens = null;
		$this->creatingLens = false;
		$this->editingThreshold = false;
		$this->saved = null;
		$this->contextFile = null;
	}

	/**
	 * Puts a lens in or out of the rounds of the project on screen.
	 */
	public function toggleLens(int $lens, bool $isEnabled): void
	{
		if ($this->project !== '') {
			ToggleLensAction::execute(new LensId(id: $lens), $this->project, $isEnabled);
			$this->forgetCheck();
		}
	}

	public function editLens(int $lens): void
	{
		$lensEntity = $this->lensById($lens);

		if ($lensEntity === null) {
			return;
		}

		$this->openLens = $lens;
		$this->lensPaths = $lensEntity->paths;
		$this->lensRules = $lensEntity->rules;
		$this->lensCommand = (string) $lensEntity->command;
		$this->lensDescription = $lensEntity->description;
		$this->newPath = '';
	}

	public function closeLens(): void
	{
		$this->openLens = null;
		$this->creatingLens = false;
	}

	public function addPath(): void
	{
		$path = trim($this->newPath);

		if ($path !== '' && ! in_array($path, $this->lensPaths, true)) {
			$this->lensPaths[] = $path;
		}

		$this->newPath = '';
	}

	public function removePath(int $index): void
	{
		unset($this->lensPaths[$index]);
		$this->lensPaths = array_values($this->lensPaths);
	}

	public function toggleRule(string $file): void
	{
		$this->lensRules = in_array($file, $this->lensRules, true)
			? array_values(array_diff($this->lensRules, [$file]))
			: [...$this->lensRules, $file];
	}

	/**
	 * Writes what the lens does: in the standard on the standard page, as
	 * this project's own on a project page. A part that matches the standard
	 * stays the standard.
	 */
	public function saveLens(): void
	{
		$lensEntity = $this->openLens === null ? null : $this->lensById($this->openLens);

		if ($lensEntity === null) {
			return;
		}

		if ($this->project === '' || $lensEntity->isOwn()) {
			UpdateStandardLensAction::execute($lensEntity->lensId, $this->lensDescription, $this->lensPaths, $this->lensRules, $this->lensCommand);
		} else {
			CustomizeLensAction::execute(
				$lensEntity->lensId,
				$this->project,
				$this->lensPaths === $lensEntity->standardPaths ? null : $this->lensPaths,
				$this->lensRules === $lensEntity->standardRules ? null : $this->lensRules,
				trim($this->lensCommand) === (string) $lensEntity->standardCommand ? null : trim($this->lensCommand),
			);
		}

		$this->forgetCheck();
		$this->openLens = null;
	}

	/**
	 * Lets the project follow the standard again for this lens.
	 */
	public function resetLens(): void
	{
		if ($this->openLens !== null && $this->project !== '') {
			CustomizeLensAction::execute(new LensId(id: $this->openLens), $this->project, null, null, null);
			$this->forgetCheck();
		}

		$this->openLens = null;
	}

	public function resetCommand(): void
	{
		$this->lensCommand = (string) ($this->openLens === null ? null : $this->lensById($this->openLens)?->standardCommand);
	}

	public function startLens(): void
	{
		$this->creatingLens = true;
		$this->openLens = null;
		$this->lensName = '';
		$this->lensDescription = '';
		$this->lensPaths = [];
		$this->lensRules = [];
		$this->lensCommand = '';
		$this->alsoInStandard = false;
		$this->newPath = '';
		$this->resetValidation();
	}

	public function createLens(): void
	{
		$this->validate([
			'lensName' => ['required', 'min:2', 'max:40', 'regex:/^[a-z0-9-]+$/'],
			'lensDescription' => ['required', 'min:5'],
		], [
			'lensName.required' => __('Geef de lens een naam.'),
			'lensName.regex' => __('Een lensnaam bestaat uit kleine letters, cijfers en streepjes.'),
			'lensDescription.required' => __('Zeg waar de lens op let.'),
		]);

		CreateLensAction::execute(
			$this->lensName,
			$this->lensDescription,
			$this->lensPaths,
			$this->lensRules,
			$this->lensCommand,
			$this->project ?: null,
			$this->alsoInStandard,
		);

		$this->forgetCheck();
		$this->creatingLens = false;
	}

	/**
	 * Writes the way of working down. It lands in every working copy at the
	 * next round, so a change here reaches the agents by itself.
	 */
	public function saveContext(): void
	{
		SaveAgentContextBlocksAction::execute($this->context);

		$this->context = GetAgentContextBlocksAction::execute();
		$this->saved = __('Opgeslagen. Elke werkkopie krijgt dit bij de volgende ronde.');
	}

	public function saveProjectContext(): void
	{
		$setting = GetWorkflowSettingAction::execute($this->project);

		SaveWorkflowSettingAction::execute($this->project, $setting->hasOwnThreshold ? $setting->maxRounds : null, $setting->hasOwnThreshold ? $setting->afterRounds : null, $this->projectContext);

		$this->saved = __('Opgeslagen. Het komt achter de tekst uit de standaard.');
	}

	/**
	 * Sets the threshold: of the standard, or of the project on screen.
	 */
	public function setThreshold(int $rounds, string $after): void
	{
		$afterRounds = AfterRoundsEnum::tryFrom($after) ?? AfterRoundsEnum::BACK_TO_YOU;

		if ($this->project === '') {
			resolve(SettingRepositoryContract::class)->saveThreshold($rounds, $afterRounds->value);

			return;
		}

		SaveWorkflowSettingAction::execute($this->project, $rounds, $afterRounds, $this->projectContext);
	}

	/**
	 * Lets the project follow the threshold of the standard again.
	 */
	public function resetThreshold(): void
	{
		if ($this->project !== '') {
			SaveWorkflowSettingAction::execute($this->project, null, null, $this->projectContext);
		}

		$this->editingThreshold = false;
	}

	/**
	 * The way of working as the board knows it, step by step.
	 *
	 * @return array<int, array{number: int, label: string, text: string}>
	 */
	private function chain(): array
	{
		return [
			['number' => 1, 'label' => __('Te doen'), 'text' => __('Ingericht: werkkopie en agent staan klaar.')],
			['number' => 2, 'label' => __('Bouwen'), 'text' => __('De agent werkt, zo veel berichten als nodig.')],
			['number' => 3, 'label' => __('Review'), 'text' => __('Het werk is gecommit; de lenzen kijken mee.')],
			['number' => 4, 'label' => __('Pull request'), 'text' => __('Gepusht. Collega\'s kijken, mergen doet een mens.')],
			['number' => 5, 'label' => __('Klaar'), 'text' => __('Gemerged. De werkkopie mag weg.')],
		];
	}

	/**
	 * The check of every project, asked at most once a minute: it reads the
	 * file list of each project.
	 *
	 * @param  Collection<int, string>  $projectNames
	 * @return array<string, array<string, mixed>>
	 */
	private function checks(Collection $projectNames): array
	{
		return Cache::remember('workflows.checks', 60, fn () => $projectNames
			->mapWithKeys(function (string $name): array {
				$check = CheckWorkflowAction::execute($name);

				// only counts go in the cache; objects do not survive it
				return [$name => [
					'broken' => $check['broken'],
					'own' => collect($check['lenses'])->filter(fn (array $lens) => $lens['lens']->isOwn())->count(),
				]];
			})
			->all());
	}

	/**
	 * The projects on the left, with what needs a look.
	 *
	 * @param  Collection<int, string>  $projectNames
	 * @param  array<string, array<string, mixed>>  $checks
	 * @return Collection<int, array{name: string, tone: string, note: ?string, noteTone: string}>
	 */
	private function index(Collection $projectNames, array $checks): Collection
	{
		return $projectNames
			->filter(fn (string $name) => $this->search === '' || str_contains(strtolower($name), strtolower($this->search)))
			->filter(fn (string $name) => ! $this->onlyAdjust || ($checks[$name]['broken'] ?? 0) > 0)
			->map(function (string $name) use ($checks): array {
				$broken = $checks[$name]['broken'] ?? 0;
				$own = $checks[$name]['own'] ?? 0;

				return [
					'name' => $name,
					'tone' => $broken > 0 ? 'amber' : 'green',
					'note' => match (true) {
						$broken > 0 => trans_choice(':count lens uit|:count lenzen uit', $broken, ['count' => $broken]),
						$own > 0 => trans_choice(':count eigen lens|:count eigen lenzen', $own, ['count' => $own]),
						default => null,
					},
					'noteTone' => $broken > 0 ? 'amber' : '',
				];
			})
			->values();
	}

	/**
	 * The lenses of a project with what the check found about each.
	 *
	 * @param  Collection<int, LensEntity>  $lenses
	 * @param  array<string, mixed>|null  $check
	 * @return Collection<int, array<string, mixed>>
	 */
	private function projectLenses(Collection $lenses, ?array $check): Collection
	{
		$checked = collect($check['lenses'] ?? [])->keyBy(fn (array $lens) => $lens['lens']->lensId->id);

		return $lenses->map(fn (LensEntity $lensEntity) => [
			'lens' => $lensEntity,
			'check' => $checked->get($lensEntity->lensId->id),
			'isCustom' => $lensEntity->isOwn() || $lensEntity->isCustomized(),
			'showsPaths' => $lensEntity->isOwn()
				|| $lensEntity->paths !== $lensEntity->standardPaths
				|| ($checked->get($lensEntity->lensId->id)['state'] ?? null) === 'off',
			'kind' => match (true) {
				$lensEntity->isOwn() => __('eigen lens'),
				$lensEntity->isCustomized() => __('aangepast'),
				default => __('standaard'),
			},
		]);
	}

	/**
	 * @param  Collection<int, LensEntity>  $lenses
	 */
	private function lensSummary(Collection $lenses): string
	{
		$standard = $lenses->reject(fn (LensEntity $lensEntity) => $lensEntity->isOwn());
		$customized = $standard->filter(fn (LensEntity $lensEntity) => $lensEntity->isCustomized())->count();
		$own = $lenses->count() - $standard->count();

		return implode(' · ', array_filter([
			trans_choice(':count uit de standaard|:count uit de standaard', $standard->count(), ['count' => $standard->count()])
				. ($customized > 0 ? ', ' . trans_choice(':count daarvan aangepast|:count daarvan aangepast', $customized, ['count' => $customized]) : ''),
			$own > 0 ? trans_choice(':count eigen|:count eigen', $own, ['count' => $own]) : null,
		]));
	}

	/**
	 * Everything the drawer of one lens shows.
	 *
	 * @param  Collection<int, LensEntity>  $lenses
	 * @param  array<string, mixed>|null  $check
	 * @return array<string, mixed>|null
	 */
	private function lensDrawer(Collection $lenses, ?array $check): ?array
	{
		$lensEntity = $lenses->first(fn (LensEntity $item) => $item->lensId->id === $this->openLens);

		if ($lensEntity === null) {
			return null;
		}

		return [
			'lens' => $lensEntity,
			'paths' => array_map(fn (string $pattern) => [
				'pattern' => $pattern,
				'count' => $this->project === '' ? null : CountProjectFilesAction::execute($this->project, $pattern),
				'isStandard' => in_array($pattern, $lensEntity->standardPaths, true),
			], $this->lensPaths),
			'rules' => $this->project === ''
				? []
				: array_map(fn (string $file) => [
					'file' => $file,
					'isPicked' => $this->isPicked($file, $lensEntity),
					'pattern' => $this->patternFor($file, $lensEntity),
				], $check['ruleFiles'] ?? []),
		];
	}

	private function isPicked(string $file, LensEntity $lensEntity): bool
	{
		return in_array($file, $this->lensRules, true) || $this->patternFor($file, $lensEntity) !== null && $this->lensRules === $lensEntity->standardRules;
	}

	private function patternFor(string $file, LensEntity $lensEntity): ?string
	{
		foreach ($lensEntity->standardRules as $pattern) {
			if (fnmatch($pattern, $file, FNM_NOESCAPE) || fnmatch($pattern, '/' . $file, FNM_NOESCAPE)) {
				return $pattern;
			}
		}

		return null;
	}

	private function lensById(int $lens): ?LensEntity
	{
		return collect(GetLensesAction::execute($this->project ?: null))
			->first(fn (LensEntity $lensEntity) => $lensEntity->lensId->id === $lens);
	}

	private function forgetCheck(): void
	{
		Cache::forget('workflows.checks');
	}

	public function render(): View
	{
		$projectNames = GetProjectsAction::execute()->pluck('name')->sort()->values();
		$checks = $this->checks($projectNames);
		$isStandard = $this->project === '';
		$check = $isStandard ? null : CheckWorkflowAction::execute($this->project);
		$lenses = collect(GetLensesAction::execute($this->project ?: null));
		$setting = GetWorkflowSettingAction::execute($this->project ?: null);

		return view('livewire.workflows', [
			'index' => $this->index($projectNames, $checks),
			'adjustCount' => collect($checks)->filter(fn (array $check) => $check['broken'] > 0)->count(),
			'isStandard' => $isStandard,
			'check' => $check,
			'chain' => $this->chain(),
			'lenses' => $isStandard
				? $lenses->filter(fn (LensEntity $lensEntity) => ! $lensEntity->isOwn())->values()
				: $this->projectLenses($lenses, $check),
			'lensSummary' => $isStandard ? null : $this->lensSummary($lenses),
			'enabledCount' => $lenses->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled)->count(),
			'setting' => $setting,
			'roundChoices' => self::ROUND_CHOICES,
			'afterChoices' => AfterRoundsEnum::cases(),
			'steps' => WorkflowStepEnum::cases(),
			'lensDrawer' => $this->openLens === null ? null : $this->lensDrawer($lenses, $check),
			'ruleFiles' => $check['ruleFiles'] ?? [],
			'view' => $this->view,
			...($this->view === 'context' ? $this->projectContextData($this->project ?: null, $this->project === '' ? null : GetProjectsAction::execute()->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)?->path) : []),
			'newPathCount' => $this->project !== '' && trim($this->newPath) !== '' ? CountProjectFilesAction::execute($this->project, trim($this->newPath)) : null,
		]);
	}
}
