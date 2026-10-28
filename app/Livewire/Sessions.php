<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithDrawerStack;
use App\Livewire\Concerns\WithFilterChips;
use App\Livewire\Concerns\WithProjectFilter;
use App\Livewire\Concerns\WithSessionActions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Session\Actions\DeleteSessionAction;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Actions\GetTranscriptAction;
use Src\Session\Enums\SessionCleanupEnum;
use Src\Session\Enums\SessionGroupEnum;
use Src\Session\Enums\SessionStatusEnum;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionId;

class Sessions extends Component
{
	use WithDrawerStack;
	use WithFilterChips;
	use WithProjectFilter;
	use WithSessionActions;

	#[Session(key: 'devdock.sessions.query')]
	public string $query = '';

	#[Session(key: 'devdock.sessions.when')]
	public string $when = '';

	#[Session(key: 'devdock.sessions.cleanup')]
	public string $cleanup = '';

	/** @var array<int, string> */
	public array $selected = [];

	#[Url(except: null)]
	public ?string $openSession = null;

	public function mount(): void
	{
		if ($this->openSession !== null) {
			$this->replaceDrawer('session', $this->openSession);
		}
	}

	public function updatedQuery(): void
	{
		$this->selected = [];
	}

	public function toggleWhen(string $when): void
	{
		$this->when = $this->when === $when ? '' : $when;
	}

	public function toggleCleanup(string $cleanup): void
	{
		$this->cleanup = $this->cleanup === $cleanup ? '' : $cleanup;
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->when = '';
		$this->cleanup = '';
	}

	public function toggleSelected(string $uuid): void
	{
		$this->selected = in_array($uuid, $this->selected, true)
			? array_values(array_diff($this->selected, [$uuid]))
			: [...$this->selected, $uuid];
	}

	public function toggleAllShown(): void
	{
		$shown = $this->shown(collect(GetSessionsAction::execute()))->pluck('uuid')->all();

		$this->selected = array_diff($shown, $this->selected) === []
			? array_values(array_diff($this->selected, $shown))
			: array_values(array_unique([...$this->selected, ...$shown]));
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

	public function render(): View
	{
		$all = collect(GetSessionsAction::execute());
		$shown = $this->shown($all);
		$cleanupOptions = $this->cleanupOptions($all);
		$sessionEntity = $this->openSessionEntity($all);

		return view('livewire.sessions', [
			'groups' => collect(SessionGroupEnum::cases())
				->map(fn (SessionGroupEnum $group) => [
					'case' => $group,
					'sessions' => $shown->filter(fn (SessionEntity $item) => $item->group === $group)->values(),
					'total' => $all->filter(fn (SessionEntity $item) => $item->group === $group)->count(),
				])
				->filter(fn (array $group) => $group['sessions']->isNotEmpty()),
			'shown' => $shown,
			'all' => $all,
			'projectOptions' => $this->projectOptions($this->countsByProject($all)),
			'projectTotal' => $all->count(),
			'whenOptions' => $this->whenOptions($all),
			'cleanupOptions' => $cleanupOptions,
			'cleanupLabel' => $this->cleanupLabel(),
			'chips' => $this->chips(),
			'diskTotal' => round($all->sum('bytes') / 1048576),
			'filtered' => filled($this->query)
				|| filled($this->when)
				|| filled($this->cleanup)
				|| $this->hasProjectFilter(),
			'session' => $sessionEntity,
			'projectPath' => $this->projectPathOf($sessionEntity),
			'drawerTab' => $this->drawerTab(),
			'transcript' => $sessionEntity === null || $this->drawerTab() !== 'Transcript'
				? collect()
				: collect(GetTranscriptAction::execute($sessionEntity->sessionId)),
		]);
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 */
	private function openSessionEntity(Collection $all): ?SessionEntity
	{
		$drawer = $this->currentDrawer();

		if ($drawer === null) {
			return null;
		}

		return $all->first(fn (SessionEntity $sessionEntity) => $sessionEntity->uuid === $drawer['id']);
	}

	/**
	 * Where the project of this session stands on disk; that is what you type in
	 * the terminal to carry on there.
	 */
	private function projectPathOf(?SessionEntity $sessionEntity): ?string
	{
		if ($sessionEntity?->project === null) {
			return null;
		}

		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $sessionEntity->project)?->path;
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @return Collection<int, SessionEntity>
	 */
	private function shown(Collection $all): Collection
	{
		return $all
			->when($this->hasProjectFilter(), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => in_array($sessionEntity->project, $this->projects, true),
			))
			->when(filled($this->cleanup), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => $this->needsCleanup($sessionEntity),
			))
			->when(filled($this->query), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => $sessionEntity->matches($this->query),
			))
			->when(filled($this->when), fn (Collection $items) => $items->filter(
				fn (SessionEntity $sessionEntity) => $sessionEntity->status->value === $this->when,
			))
			->values();
	}

	private function needsCleanup(SessionEntity $sessionEntity): bool
	{
		return match (SessionCleanupEnum::from($this->cleanup)) {
			SessionCleanupEnum::ABANDONED => $sessionEntity->status === SessionStatusEnum::ABANDONED,
			SessionCleanupEnum::LARGE => $sessionEntity->isLarge,
			SessionCleanupEnum::UNNAMED => $sessionEntity->title === null,
			SessionCleanupEnum::TICKET_MISMATCH => $sessionEntity->mismatchedTicket() !== null,
		};
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @return Collection<string, int>
	 */
	private function countsByProject(Collection $all): Collection
	{
		return $all->map(fn (SessionEntity $sessionEntity) => $sessionEntity->project)->countBy();
	}

	/**
	 * @param  Collection<int, SessionEntity>  $all
	 * @return Collection<int, array{case: SessionStatusEnum, count: int}>
	 */
	private function whenOptions(Collection $all): Collection
	{
		return collect(SessionStatusEnum::cases())->map(fn (SessionStatusEnum $status) => [
			'case' => $status,
			'count' => $all->filter(fn (SessionEntity $item) => $item->status === $status)->count(),
		]);
	}

	/**
	 * The sessions that ask to be cleaned up, each with how many stand that way.
	 *
	 * @param  Collection<int, SessionEntity>  $all
	 * @return Collection<int, array{case: SessionCleanupEnum, count: int}>
	 */
	private function cleanupOptions(Collection $all): Collection
	{
		return collect(SessionCleanupEnum::cases())->map(fn (SessionCleanupEnum $case) => [
			'case' => $case,
			'count' => $all->filter(fn (SessionEntity $item) => match ($case) {
				SessionCleanupEnum::ABANDONED => $item->status === SessionStatusEnum::ABANDONED,
				SessionCleanupEnum::LARGE => $item->isLarge,
				SessionCleanupEnum::UNNAMED => $item->title === null,
				SessionCleanupEnum::TICKET_MISMATCH => $item->mismatchedTicket() !== null,
			})->count(),
		]);
	}

	private function cleanupLabel(): string
	{
		return blank($this->cleanup) ? __('Alles') : SessionCleanupEnum::from($this->cleanup)->label();
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
			filled($this->when) ? ['label' => SessionStatusEnum::from($this->when)->label(), 'filter' => 'when'] : null,
			filled($this->cleanup) ? ['label' => $this->cleanupLabel(), 'filter' => 'cleanup'] : null,
			...$this->projectChips(),
		]));
	}
}
