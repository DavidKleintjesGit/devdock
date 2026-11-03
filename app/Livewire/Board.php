<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithProjectFilter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Session;
use Livewire\Component;
use Src\Board\Actions\CreateTodoAction;
use Src\Board\Actions\GetBoardAction;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\BoardSignalEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\TodoObject;
use Src\Fetch\Actions\StartFetchAction;
use Src\Project\Actions\GetProjectsAction;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;

class Board extends Component
{
	/**
	 * How many backlog cards stand in the column before "Nog n in de backlog".
	 */
	private const BACKLOG_SHOWN = 8;

	use WithProjectFilter;

	/**
	 * A finished round of fetching brings the page up to date, and so does
	 * anything the side panel changed.
	 */
	protected $listeners = ['fetched' => '$refresh', 'board-changed' => '$refresh'];

	/**
	 * The board as built in this request; never sent to the browser.
	 *
	 * @var Collection<int, CardObject>|null
	 */
	private ?Collection $cardObjects = null;

	#[Session(key: 'devdock.board.query')]
	public string $query = '';

	#[Session(key: 'devdock.board.attention')]
	public string $attention = '';

	#[Session(key: 'devdock.board.backlog')]
	public ?bool $showBacklog = null;

	/** @var array<int, string> */
	#[Session(key: 'devdock.board.columns')]
	public array $shownColumns = [];

	public string $mobileColumn = '';

	public bool $addingTodo = false;

	public string $todoTitle = '';

	public string $todoNote = '';

	public string $todoProject = '';

	public bool $todoLater = false;

	public bool $backlogExpanded = false;

	/**
	 * Any write to the database can change what the board shows, so it
	 * also clears the board this request already built.
	 */
	public function boot(): void
	{
		DB::listen(function (QueryExecuted $queryExecuted): void {
			if (! str_starts_with(ltrim(strtolower($queryExecuted->sql)), 'select')) {
				$this->cardObjects = null;
			}
		});
	}

	public function mount(): void
	{
		// the session already put a choice in here when you were here before
		// een naam die niet meer bestaat komt uit een keuze van je van voor een
		// verbouwing, en zou die kolom voorgoed onzichtbaar houden
		$this->shownColumns = array_values(array_filter(
			$this->shownColumns,
			fn (string $column) => BoardColumnEnum::tryFrom($column) !== null,
		));

		if ($this->shownColumns === []) {
			$this->shownColumns = array_values(array_filter(
				(array) config('devdock.board.columns'),
				fn (string $column) => BoardColumnEnum::tryFrom($column) !== null,
			)) ?: array_column(BoardColumnEnum::sprint(), 'value');
		}

		$this->showBacklog ??= ! config('devdock.board.backlog_hidden');
		$this->mobileColumn = $this->shownColumns[0] ?? BoardColumnEnum::TO_DO->value;
	}

	public function startTodo(): void
	{
		$this->addingTodo = true;
		$this->todoTitle = '';
		$this->todoNote = '';
		$this->todoProject = '';
		$this->todoLater = false;
		$this->resetValidation();
	}

	public function cancelTodo(): void
	{
		$this->addingTodo = false;
	}

	public function createTodo(): void
	{
		$this->validate([
			'todoTitle' => ['required', 'min:3'],
		], [
			'todoTitle.required' => __('Geef je to-do een titel.'),
			'todoTitle.min' => __('Geef je to-do een titel.'),
		]);

		$todoEntity = CreateTodoAction::execute(TodoObject::from([
			'title' => $this->todoTitle,
			'note' => $this->todoNote ?: null,
			'project' => $this->todoProject ?: null,
			'is_later' => $this->todoLater,
		]));

		$this->addingTodo = false;
		$this->dispatch('open-card', card: $todoEntity->boardKey())->to(Drawer::class);
	}

	/**
	 * Hands this task a fresh agent. The one that was in charge stays on the
	 * task, so you can always read back what it did.
	 */
	/**
	 * Has the session that made the changes write the commit message. It
	 * knows what it did; the board only sees file names.
	 */
	/**
	 * Fetches Jira and Bitbucket again, for when the card looks behind.
	 */
	public function fetchNow(): void
	{
		StartFetchAction::execute();
	}

	public function toggleBacklog(): void
	{
		$this->showBacklog = ! $this->showBacklog;
	}

	public function toggleAttention(string $filter): void
	{
		$this->attention = $this->attention === $filter ? '' : $filter;
	}

	public function toggleColumn(string $column): void
	{
		$this->shownColumns = in_array($column, $this->shownColumns, true)
			? array_values(array_diff($this->shownColumns, [$column]))
			: [...$this->shownColumns, $column];
	}

	public function showAllColumns(): void
	{
		$this->shownColumns = array_column(BoardColumnEnum::sprint(), 'value');
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->attention = '';
		$this->clearProjects();
	}

	/**
	 * The line under the title: the sprint, how long it still runs, and how
	 * much of it waits on you.
	 *
	 * @param  Collection<int, CardObject>  $cardObjects
	 */
	private function summary(Collection $cardObjects): string
	{
		$sprintEntity = collect(GetTicketsAction::execute())
			->first(fn (TicketEntity $ticketEntity) => $ticketEntity->isInSprint());

		$tasks = $cardObjects->filter(
			fn (CardObject $card) => ! in_array($card->column, [BoardColumnEnum::LATER, BoardColumnEnum::DONE], true),
		);
		$yours = $tasks->filter(fn (CardObject $card) => $card->isYourTurn())->count();

		$parts = [];

		if ($sprintEntity !== null) {
			$parts[] = $sprintEntity->sprint;

			if ($sprintEntity->sprintEndsAt !== null) {
				$workdays = (int) CarbonImmutable::today()->diffInWeekdays($sprintEntity->sprintEndsAt->startOfDay(), absolute: false);

				$parts[] = $workdays > 0
					? trans_choice('nog :count werkdag|nog :count werkdagen', $workdays, ['count' => $workdays])
					: __('laatste dag');
			}
		}

		$parts[] = trans_choice(':count taak|:count taken', $tasks->count(), ['count' => $tasks->count()]);
		$parts[] = __(':count jouw beurt', ['count' => $yours]);

		return implode(' · ', $parts);
	}

	/**
	 * The signals to filter on, each with how many cards carry it.
	 *
	 * @param  Collection<int, CardObject>  $cardObjects
	 * @return Collection<int, array{case: BoardSignalEnum, count: int}>
	 */
	private function signals(Collection $cardObjects): Collection
	{
		return collect(BoardSignalEnum::cases())->map(fn (BoardSignalEnum $signal) => [
			'case' => $signal,
			'count' => $cardObjects->filter(fn (CardObject $card) => $signal->matches($card))->count(),
			'title' => $signal === BoardSignalEnum::STALLED
				? __(':days dagen geen commit, agentbericht, reviewronde of reactie', ['days' => (int) config('devdock.board.stale_days')])
				: null,
		]);
	}

	/**
	 * The cards that are left after every filter.
	 *
	 * @param  Collection<int, CardObject>  $cardObjects
	 * @return Collection<int, CardObject>
	 */
	private function visible(Collection $cardObjects): Collection
	{
		return $cardObjects
			// filter je op een project, dan hoort een taak zonder project er niet
			// bij: die hoort nergens bij
			->when($this->hasProjectFilter(), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => in_array($card->project, $this->projects, true),
			))
			->when(filled($this->attention), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => BoardSignalEnum::from($this->attention)->matches($card),
			))
			->when(filled($this->query), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => $card->matches($this->query),
			))
			->values();
	}

	/**
	 * The name the branch should carry, when it does not carry the ticket
	 * number yet. Everything hangs on that number.
	 */
	/**
	 * The name a new branch would carry. With a ticket number the answer is
	 * fixed, so nothing has to be asked; without one the title is the best
	 * the board can do on its own, and the agent can be asked for better.
	 */
	/**
	 * @param  Collection<int, CardObject>  $cardObjects
	 */
	/**
	 * The board of this request. Building it reads every source, so it is
	 * built once and shared by the actions and the render that follows.
	 *
	 * @return Collection<int, CardObject>
	 */
	private function board(): Collection
	{
		return $this->cardObjects ??= collect(GetBoardAction::execute());
	}

	/**
	 * How many cards each project has, for the project picker.
	 *
	 * @param  Collection<int, CardObject>  $cardObjects
	 * @return Collection<string, int>
	 */
	private function countsByProject(Collection $cardObjects): Collection
	{
		return $cardObjects
			->filter(fn (CardObject $cardObject) => $cardObject->project !== null)
			->map(fn (CardObject $cardObject) => $cardObject->project)
			->countBy();
	}

	public function render(): View
	{
		$cardObjects = $this->board();
		$visible = $this->visible($cardObjects);

		$columns = collect(BoardColumnEnum::cases())
			->filter(fn (BoardColumnEnum $column) => $column === BoardColumnEnum::LATER
				? $this->showBacklog
				: in_array($column->value, $this->shownColumns, true))
			->map(function (BoardColumnEnum $column) use ($cardObjects, $visible): array {
				$cards = $visible->filter(fn (CardObject $card) => $card->column === $column)->values();

				// de backlog kan lang zijn; de rest staat een klik verder
				$limit = $column === BoardColumnEnum::LATER && ! $this->backlogExpanded ? self::BACKLOG_SHOWN : null;

				return [
					'case' => $column,
					'total' => $cardObjects->filter(fn (CardObject $card) => $card->column === $column)->count(),
					'yours' => $cards->filter(fn (CardObject $card) => $card->isYourTurn())->count(),
					'conflicts' => $cards->filter(fn (CardObject $card) => $card->hasConflict())->count(),
					'cards' => $limit === null ? $cards : $cards->take($limit),
					'hidden' => $limit === null ? 0 : max(0, $cards->count() - $limit),
				];
			})
			->values();

		if (! $columns->contains(fn (array $column) => $column['case']->value === $this->mobileColumn) && $columns->isNotEmpty()) {
			$this->mobileColumn = $columns->first()['case']->value;
		}

		return view('livewire.board', [
			'columns' => $columns,
			'allColumns' => collect(BoardColumnEnum::sprint())->map(fn (BoardColumnEnum $column) => [
				'case' => $column,
				'total' => $cardObjects->filter(fn (CardObject $card) => $card->column === $column)->count(),
				'isShown' => in_array($column->value, $this->shownColumns, true),
			]),
			'shownCount' => count($this->shownColumns) + ($this->showBacklog ? 1 : 0),
			'projectOptions' => $this->projectOptions($this->countsByProject($cardObjects)),
			'projectTotal' => $cardObjects->count(),
			'signals' => $this->signals($cardObjects),
			'allProjectNames' => GetProjectsAction::execute()->pluck('name')->sort()->values(),
			'backlogCount' => $cardObjects->filter(fn (CardObject $card) => $card->column === BoardColumnEnum::LATER)->count(),
			'summary' => $this->summary($cardObjects),
			'visibleCount' => $visible->count(),
			'totalCount' => $cardObjects->count(),
			'filtered' => filled($this->query)
				|| $this->hasProjectFilter()
				|| filled($this->attention),
		]);
	}
}
