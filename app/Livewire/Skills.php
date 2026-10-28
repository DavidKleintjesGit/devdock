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
use Src\Skill\Actions\CopySkillToProjectAction;
use Src\Skill\Actions\CountSkillFilesInPlaceAction;
use Src\Skill\Actions\CreateSkillAction;
use Src\Skill\Actions\GetSkillContentsAction;
use Src\Skill\Actions\GetSkillsAction;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Objects\SkillEntity;

class Skills extends Component
{
	use WithFilterChips;
	use WithProjectFilter;

	#[Session(key: 'devdock.skills.scope')]
	public string $scope = '';

	#[Session(key: 'devdock.skills.query')]
	public string $query = '';

	#[Url(as: 'skill', except: null)]
	public ?string $openSkill = null;

	public bool $creating = false;

	public string $newName = '';

	public string $newDescription = '';

	public string $newWhere = SkillScopeEnum::GLOBALLY->value;

	public bool $actionsOpen = false;

	public bool $copying = false;

	public string $copyTo = '';

	public ?string $done = null;

	public function toggleActions(): void
	{
		$this->actionsOpen = ! $this->actionsOpen;
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

	/**
	 * Puts the whole skill folder in the chosen project; the panel stays open.
	 */
	public function copy(): void
	{
		$skillEntity = $this->openSkillEntity(collect(GetSkillsAction::execute()));

		if ($skillEntity !== null && filled($this->copyTo)) {
			$copied = CopySkillToProjectAction::execute($skillEntity->skillId, $this->copyTo);
			$this->done = $copied ? __('Overgenomen in :project.', ['project' => $this->copyTo]) : null;
		}

		$this->copying = false;
	}

	public function toggleScope(string $scope): void
	{
		$this->scope = $this->scope === $scope ? '' : $scope;
	}

	public function open(string $name): void
	{
		$this->openSkill = $name;
		$this->creating = false;
		$this->actionsOpen = false;
		$this->copying = false;
		$this->done = null;
	}

	public function startCreating(): void
	{
		$this->creating = true;
		$this->openSkill = null;
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

		$written = CreateSkillAction::execute($this->newName, $this->newDescription, $this->newWhere);

		if ($written === null) {
			$this->addError('newName', __('Er bestaat hier al een skill met deze naam.'));

			return;
		}

		$this->creating = false;
		$this->openSkill = $this->newName;
		$this->newName = '';
		$this->newDescription = '';
	}

	public function closeDrawer(): void
	{
		$this->openSkill = null;
		$this->creating = false;
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->scope = '';
		$this->clearProjects();
	}

	public function render(): View
	{
		$all = collect(GetSkillsAction::execute());

		$shown = $all
			->when(filled($this->query), fn (Collection $items) => $items->filter(
				fn (SkillEntity $skillEntity) => $skillEntity->matches($this->query),
			))
			->when($this->hasProjectFilter(), fn (Collection $items) => $items->filter(
				fn (SkillEntity $skillEntity) => $skillEntity->project === null
					|| in_array($skillEntity->project, $this->projects, true),
			))
			->values();

		$skillEntity = $this->openSkillEntity($all);
		$projectNames = GetProjectsAction::execute()->pluck('name');

		return view('livewire.skills', [
			'groups' => collect(SkillScopeEnum::cases())
				->filter(fn (SkillScopeEnum $case) => $this->scope === '' || $this->scope === $case->value)
				->map(fn (SkillScopeEnum $case) => [
					'case' => $case,
					'skills' => $shown->filter(fn (SkillEntity $item) => $item->scope === $case)->values(),
				]),
			'scopes' => collect(SkillScopeEnum::cases())->map(fn (SkillScopeEnum $case) => [
				'case' => $case,
				'count' => $all->filter(fn (SkillEntity $item) => $item->scope === $case)->count(),
			]),
			'total' => $all->count(),
			'chips' => $this->chips(),
			'projectOptions' => $this->projectOptions($this->countsByProject($all)),
			'projectTotal' => $all->filter(fn (SkillEntity $item) => $item->project !== null)->count(),
			'projects' => $projectNames,
			'globalScope' => SkillScopeEnum::GLOBALLY->value,
			'projectsToCopyTo' => $projectNames->reject(fn (string $name) => $name === $skillEntity?->project)->values(),
			'copyWarning' => $this->copyWarning($skillEntity),
			'skill' => $skillEntity,
			'body' => $skillEntity === null
				? null
				: resolve(RenderMarkdown::class)->toHtml((string) GetSkillContentsAction::execute($skillEntity->skillId)),
		]);
	}

	/**
	 * @param  Collection<int, SkillEntity>  $all
	 */
	private function openSkillEntity(Collection $all): ?SkillEntity
	{
		if ($this->openSkill === null) {
			return null;
		}

		return $all
			->first(fn (SkillEntity $skillEntity) => $skillEntity->name === $this->openSkill);
	}

	/**
	 * How many skills each project has, for the project picker.
	 *
	 * @param  Collection<int, SkillEntity>  $all
	 * @return Collection<string, int>
	 */
	private function countsByProject(Collection $all): Collection
	{
		return $all
			->filter(fn (SkillEntity $skillEntity) => $skillEntity->project !== null)
			->map(fn (SkillEntity $skillEntity) => $skillEntity->project)
			->countBy();
	}

	/**
	 * What already stands under this skill name in the chosen project; that goes
	 * under.
	 */
	private function copyWarning(?SkillEntity $skillEntity): ?string
	{
		if ($skillEntity === null || blank($this->copyTo)) {
			return null;
		}

		$filesInPlace = CountSkillFilesInPlaceAction::execute($skillEntity->skillId, $this->copyTo);

		if ($filesInPlace === 0) {
			return null;
		}

		return trans_choice(
			'Daar staat al een skill met deze naam; :count bestand wordt overschreven.|Daar staat al een skill met deze naam; :count bestanden worden overschreven.',
			$filesInPlace,
			['count' => $filesInPlace],
		);
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
			filled($this->scope) ? ['label' => SkillScopeEnum::from($this->scope)->label(), 'filter' => 'scope'] : null,
			...$this->projectChips(),
		]));
	}
}
