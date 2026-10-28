<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithFilterChips;
use App\Livewire\Concerns\WithProjectFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Project\Actions\GetProjectsAction;
use Src\Rule\Actions\CopyRuleFileFromCoreAction;
use Src\Rule\Actions\CopyRuleFileToProjectAction;
use Src\Rule\Actions\GetRuleFileContentsAction;
use Src\Rule\Actions\GetRuleFilesAction;
use Src\Rule\Actions\UpdateRuleFilesFromCoreAction;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Enums\RuleOriginEnum;
use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileId;

class Rules extends Component
{
	use WithFilterChips;
	use WithProjectFilter;

	#[Session(key: 'devdock.rules.query')]
	public string $query = '';

	#[Session(key: 'devdock.rules.origin')]
	public string $origin = '';

	// the id instead of the path: a path with slashes does not survive the url,
	// and an id is unambiguous
	#[Url(as: 'file', except: null)]
	public ?int $openFileId = null;

	#[Url(as: 'tab', except: 'Inhoud')]
	public string $drawerTab = 'Inhoud';

	public bool $actionsOpen = false;

	public bool $copying = false;

	public string $copyTo = '';

	public string $confirming = '';

	public ?string $done = null;

	public function toggleActions(): void
	{
		$this->actionsOpen = ! $this->actionsOpen;
	}

	public function askUpdateFromCore(): void
	{
		$this->confirming = 'core';
		$this->actionsOpen = false;
	}

	public function askUpdateAll(): void
	{
		$this->confirming = 'all';
	}

	public function cancelConfirm(): void
	{
		$this->confirming = '';
	}

	/**
	 * Only after the confirmation does core really go over it; until then
	 * nothing is overwritten.
	 */
	public function confirmUpdate(): void
	{
		if ($this->confirming === 'core' && $this->openFileId !== null) {
			$updated = CopyRuleFileFromCoreAction::execute(new RuleFileId(id: $this->openFileId));
			$this->done = $updated ? __('Bijgewerkt vanuit mtvtd-core.') : null;
		}

		if ($this->confirming === 'all') {
			$updated = count(UpdateRuleFilesFromCoreAction::execute($this->projects));
			$this->done = trans_choice(
				':count bestand bijgewerkt.|:count bestanden bijgewerkt.',
				$updated,
				['count' => $updated],
			);
		}

		$this->confirming = '';
		$this->actionsOpen = false;
	}

	public function startCopy(): void
	{
		$this->copying = true;
		$this->copyTo = '';
		$this->actionsOpen = false;
	}

	public function cancelCopy(): void
	{
		$this->copying = false;
	}

	public function copy(): void
	{
		if ($this->openFileId !== null && filled($this->copyTo)) {
			$copied = CopyRuleFileToProjectAction::execute(new RuleFileId(id: $this->openFileId), $this->copyTo);
			$this->done = $copied ? __('Overgenomen in :project.', ['project' => $this->copyTo]) : null;
		}

		$this->copying = false;
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->origin = '';
		$this->clearProjects();
	}

	public function toggleOrigin(string $origin): void
	{
		$this->origin = $this->origin === $origin ? '' : $origin;
	}

	public function open(int $id): void
	{
		$this->openFileId = $id;
		$this->drawerTab = 'Inhoud';
		$this->actionsOpen = false;
		$this->copying = false;
		$this->done = null;
	}

	public function closeDrawer(): void
	{
		$this->openFileId = null;
	}

	public function render(): View
	{
		$everything = collect(GetRuleFilesAction::execute());

		$all = $everything
			->filter(fn (RuleFileEntity $ruleFileEntity) => ! $this->hasProjectFilter()
				|| in_array($ruleFileEntity->project, $this->projects, true))
			->values();

		$shown = $all
			->when(filled($this->origin), fn (Collection $files) => $files->filter(
				fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->origin->value === $this->origin,
			))
			->when(filled($this->query), fn (Collection $files) => $files->filter(
				fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->matches($this->query),
			));

		$behind = $all->filter(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->isBehindCore())->values();
		$ruleFileEntity = $all->first(fn (RuleFileEntity $file) => $file->ruleFileId->id === $this->openFileId);

		return view('livewire.rules', [
			'groups' => collect(RuleGroupEnum::cases())
				->map(fn (RuleGroupEnum $group) => [
					'case' => $group,
					'files' => $shown->filter(fn (RuleFileEntity $file) => $file->group === $group)->values(),
				])
				->filter(fn (array $group) => $group['files']->isNotEmpty()),
			'projectOptions' => $this->projectOptions($this->countsByProject($everything)),
			'projectTotal' => $everything->count(),
			'origins' => $this->originOptions($all),
			'behind' => $behind,
			'behindNames' => $behind->map(fn (RuleFileEntity $file) => $file->name())->unique()->sort()->values(),
			'behindProjects' => $behind->pluck('project')->unique()->count(),
			'behindInSelection' => $behind->count(),
			'total' => $all->count(),
			'chips' => $this->chips(),
			'lines' => $all->sum('lines'),
			'words' => $all->sum('words'),
			'file' => $ruleFileEntity,
			'projectsToCopyTo' => GetProjectsAction::execute()
				->pluck('name')
				->reject(fn (string $name) => $name === $ruleFileEntity?->project)
				->values(),
			'copyWarning' => $this->copyWarning($everything, $ruleFileEntity),
			'elsewhere' => $this->sameFileElsewhere($everything, $ruleFileEntity),
			'body' => $ruleFileEntity === null || $this->drawerTab !== 'Inhoud'
				? null
				: resolve(RenderMarkdown::class)->toHtml(
					(string) GetRuleFileContentsAction::execute($ruleFileEntity->ruleFileId),
				),
		]);
	}

	/**
	 * How many rule files each project has, for the project picker.
	 *
	 * @param  Collection<int, RuleFileEntity>  $everything
	 * @return Collection<string, int>
	 */
	private function countsByProject(Collection $everything): Collection
	{
		return $everything
			->map(fn (RuleFileEntity $ruleFileEntity) => $ruleFileEntity->project)
			->countBy();
	}

	/**
	 * What already stands in this place in the chosen project; that goes under.
	 */
	private function copyWarning(Collection $everything, ?RuleFileEntity $ruleFileEntity): ?string
	{
		if ($ruleFileEntity === null || blank($this->copyTo)) {
			return null;
		}

		$inPlace = $everything
			->first(fn (RuleFileEntity $other) => $other->project === $this->copyTo
				&& $other->path === $ruleFileEntity->path);

		if ($inPlace === null) {
			return null;
		}

		return __('In :project staat dit bestand al (:count regels); het wordt overschreven.', [
			'project' => $this->copyTo,
			'count' => $inPlace->lines,
		]);
	}

	/**
	 * What is switched on now, so you can let it go again.
	 *
	 * @return array<int, array{label: string, filter: string}>
	 */
	private function chips(): array
	{
		return array_values(array_filter([
			filled($this->query) ? ['label' => __('Zoeken: :term', ['term' => $this->query]), 'filter' => 'query'] : null,
			filled($this->origin) ? ['label' => RuleOriginEnum::from($this->origin)->label(), 'filter' => 'origin'] : null,
			...$this->projectChips(),
		]));
	}

	/**
	 * @param  Collection<int, RuleFileEntity>  $all
	 * @return Collection<int, array{case: RuleOriginEnum, count: int}>
	 */
	private function originOptions(Collection $all): Collection
	{
		return collect(RuleOriginEnum::cases())->map(fn (RuleOriginEnum $origin) => [
			'case' => $origin,
			'count' => $all->filter(fn (RuleFileEntity $file) => $file->origin === $origin)->count(),
		]);
	}

	/**
	 * The same file name in the other projects, so you see where else it stands
	 * and whether that copy is up to date.
	 *
	 * @return Collection<int, RuleFileEntity>
	 */
	private function sameFileElsewhere(Collection $everything, ?RuleFileEntity $ruleFileEntity): Collection
	{
		if ($ruleFileEntity === null) {
			return collect();
		}

		return $everything
			->filter(fn (RuleFileEntity $other) => $other->path === $ruleFileEntity->path
				&& $other->project !== $ruleFileEntity->project)
			->sortBy('project')
			->values();
	}
}
