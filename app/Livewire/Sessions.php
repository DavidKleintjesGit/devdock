<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithDrawerStack;
use App\Livewire\Concerns\WithProjectFilter;
use App\Livewire\Concerns\WithSessionActions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Session\Actions\DeleteSessionAction;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Actions\GetSessionTraitsAction;
use Src\Session\Enums\SessionTraitEnum;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionId;

class Sessions extends Component
{
	use WithDrawerStack;
	use WithProjectFilter;
	use WithSessionActions;

	/**
	 * How many traits a row shows before it says how many more there are.
	 */
	private const TRAITS_ON_ROW = 3;

	/**
	 * A finished round of fetching brings the page up to date.
	 */
	protected $listeners = ['fetched' => '$refresh'];

	#[Session(key: 'devdock.sessions.query')]
	public string $query = '';

	/** @var array<int, string> */
	#[Session(key: 'devdock.sessions.traits')]
	public array $traits = [];

	#[Session(key: 'devdock.sessions.traits-open')]
	public bool $traitsOpen = true;

	/** @var array<int, string> */
	public array $selected = [];

	#[Url(except: null)]
	public ?string $openSession = null;

	public function mount(): void
	{
		$this->traits = array_values(array_filter(
			$this->traits,
			fn (string $trait) => SessionTraitEnum::tryFrom($trait) !== null,
		));

		if ($this->openSession !== null) {
			$this->replaceDrawer('session', $this->openSession);
		}
	}

	public function updatedQuery(): void
	{
		$this->selected = [];
	}

	public function toggleTrait(string $trait): void
	{
		$this->traits = in_array($trait, $this->traits, true)
			? array_values(array_diff($this->traits, [$trait]))
			: [...$this->traits, $trait];
		$this->selected = [];
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->traits = [];
		$this->selected = [];
		$this->clearProjects();
	}

	public function toggleSelected(string $uuid): void
	{
		$this->selected = in_array($uuid, $this->selected, true)
			? array_values(array_diff($this->selected, [$uuid]))
			: [...$this->selected, $uuid];
	}

	/**
	 * Picks every session in view that nothing protects, or lets them all go.
	 */
	public function toggleAllShown(): void
	{
		$traitsByUuid = GetSessionTraitsAction::execute();
		$selectable = $this->shown(collect(GetSessionsAction::execute()), $traitsByUuid)
			->reject(fn (SessionEntity $sessionEntity) => $this->isProtected($traitsByUuid[$sessionEntity->uuid] ?? []))
			->pluck('uuid')
			->all();

		$this->selected = array_diff($selectable, $this->selected) === [] ? [] : $selectable;
	}

	public function clearSelection(): void
	{
		$this->selected = [];
		$this->confirmingDelete = false;
	}

	public function open(string $uuid): void
	{
		$this->openSession = $uuid;
		$this->renaming = false;
		$this->wholeTranscript = false;
		$this->replaceDrawer('session', $uuid);
	}

	/**
	 * Closing clears the url too, so a reload does not open a panel you just
	 * closed again.
	 */
	public function closeDrawer(): void
	{
		$this->openSession = null;
		$this->drawerStack = [];
		$this->renaming = false;
		$this->closeActions();
	}

	public function deleteSelected(): void
	{
		if (! $this->confirmingDelete) {
			$this->confirmingDelete = true;

			return;
		}

		foreach ($this->selected as $uuid) {
			DeleteSessionAction::execute(new SessionId(uuid: $uuid));
		}

		$this->selected = [];
		$this->confirmingDelete = false;
		$this->openSession = null;
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @param  array<string, array<int, SessionTraitEnum>>  $traitsByUuid
	 * @return Collection<int, SessionEntity>
	 */
	private function shown(Collection $all, array $traitsByUuid): Collection
	{
		return $all
			->when($this->hasProjectFilter(), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => in_array($sessionEntity->project, $this->projects, true),
			))
			->when(filled($this->query), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => $sessionEntity->matches($this->query),
			))
			// traits stack: a session has to carry every one you switched on
			->filter(fn (SessionEntity $sessionEntity) => array_diff(
				$this->traits,
				array_map(fn (SessionTraitEnum $trait) => $trait->value, $traitsByUuid[$sessionEntity->uuid] ?? []),
			) === [])
			->values();
	}

	/**
	 * One line of the list, ready to show.
	 *
	 * @param  array<int, SessionTraitEnum>  $traits
	 * @return array<string, mixed>
	 */
	private function row(SessionEntity $sessionEntity, array $traits): array
	{
		$shownTraits = collect($traits)->reject(fn (SessionTraitEnum $trait) => $trait->protects())->values();

		return [
			'session' => $sessionEntity,
			'traits' => $shownTraits->take(self::TRAITS_ON_ROW)->all(),
			'moreTraits' => max(0, $shownTraits->count() - self::TRAITS_ON_ROW),
			'canGo' => $this->canGo($traits),
			'isProtected' => $this->isProtected($traits),
			'isSelected' => in_array($sessionEntity->uuid, $this->selected, true),
			'age' => $sessionEntity->lastActiveAt === null
				? '–'
				: __(':count d', ['count' => (int) $sessionEntity->lastActiveAt->diffInDays(now(), absolute: true)]),
		];
	}

	/**
	 * The four groups of traits, each trait with how many sessions carry it.
	 *
	 * @param  Collection<int, SessionEntity>  $all
	 * @param  array<string, array<int, SessionTraitEnum>>  $traitsByUuid
	 * @return Collection<string, array<int, array{case: SessionTraitEnum, count: int, isOn: bool}>>
	 */
	private function traitGroups(Collection $all, array $traitsByUuid): Collection
	{
		return collect(SessionTraitEnum::cases())
			->map(fn (SessionTraitEnum $trait) => [
				'case' => $trait,
				'count' => $all->filter(fn (SessionEntity $sessionEntity) => in_array($trait, $traitsByUuid[$sessionEntity->uuid] ?? [], true))->count(),
				'isOn' => in_array($trait->value, $this->traits, true),
			])
			->groupBy(fn (array $trait) => $trait['case']->group())
			->map(fn (Collection $group) => $group->values()->all());
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @param  Collection<int, SessionEntity>  $canGo
	 */
	private function summary(Collection $all, Collection $canGo): string
	{
		return implode(' · ', [
			trans_choice(':count gesprek|:count gesprekken', $all->count(), ['count' => $all->count()]),
			__(':size op schijf', ['size' => $this->megabytes((int) $all->sum('bytes'))]),
			trans_choice(':count kan weg, samen :size|:count kunnen weg, samen :size', $canGo->count(), [
				'count' => $canGo->count(),
				'size' => $this->megabytes((int) $canGo->sum('bytes')),
			]),
		]);
	}

	/**
	 * @param  array<int, SessionTraitEnum>  $traits
	 */
	private function isProtected(array $traits): bool
	{
		return collect($traits)->contains(fn (SessionTraitEnum $trait) => $trait->protects());
	}

	/**
	 * @param  array<int, SessionTraitEnum>  $traits
	 */
	private function canGo(array $traits): bool
	{
		return ! $this->isProtected($traits) && count($traits) >= self::TRAITS_TO_GO;
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @param  array<string, array<int, SessionTraitEnum>>  $traitsByUuid
	 * @return array<string, mixed>|null
	 */
	private function openSessionDrawer(Collection $all, array $traitsByUuid): ?array
	{
		$drawer = $this->currentDrawer();
		$sessionEntity = $drawer === null
			? null
			: $all->first(fn (SessionEntity $item) => $item->uuid === $drawer['id']);

		return $sessionEntity === null ? null : $this->sessionDrawer($sessionEntity, $traitsByUuid[$sessionEntity->uuid] ?? []);
	}

	public function render(): View
	{
		$all = collect(GetSessionsAction::execute());
		$traitsByUuid = GetSessionTraitsAction::execute();
		$shown = $this->shown($all, $traitsByUuid);
		$canGo = $all->filter(fn (SessionEntity $sessionEntity) => $this->canGo($traitsByUuid[$sessionEntity->uuid] ?? []));
		$selectable = $shown->reject(fn (SessionEntity $sessionEntity) => $this->isProtected($traitsByUuid[$sessionEntity->uuid] ?? []));
		$picked = $all->filter(fn (SessionEntity $sessionEntity) => in_array($sessionEntity->uuid, $this->selected, true));

		return view('livewire.sessions', [
			'rows' => $shown->map(fn (SessionEntity $sessionEntity) => $this->row($sessionEntity, $traitsByUuid[$sessionEntity->uuid] ?? [])),
			'summary' => $this->summary($all, $canGo),
			'result' => __(':shown van :total in beeld · :size', [
				'shown' => $shown->count(),
				'total' => $all->count(),
				'size' => $this->megabytes((int) $shown->sum('bytes')),
			]),
			'traitGroups' => $this->traitGroups($all, $traitsByUuid),
			'activeTraits' => array_map(fn (string $trait) => SessionTraitEnum::from($trait), $this->traits),
			'selectableCount' => $selectable->count(),
			'protectedCount' => $shown->count() - $selectable->count(),
			'pickedLine' => __(':count gekozen · :size komt vrij', ['count' => $picked->count(), 'size' => $this->megabytes((int) $picked->sum('bytes'))]),
			'projectOptions' => $this->projectOptions($all->map(fn (SessionEntity $sessionEntity) => $sessionEntity->project)->countBy()),
			'projectTotal' => $all->count(),
			'filtered' => filled($this->query) || $this->traits !== [] || $this->hasProjectFilter(),
			'drawer' => $this->openSessionDrawer($all, $traitsByUuid),
		]);
	}
}
