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
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Actions\CreateTodoAction;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\BoardSignalEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\TodoObject;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\PullRequest\Actions\BuildReviewPromptAction;
use Src\PullRequest\Actions\GetReviewCommentsAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\ReviewCommentEntity;
use Src\Prompt\Actions\GetLatestPromptRunAction;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Objects\PromptRunObject;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Actions\GetTranscriptAction;
use Src\Session\Objects\SessionEntity;

class Board extends Component
{
	use WithDrawerStack;
	use WithFilterChips;
	use WithProjectFilter;
	use WithSessionActions;

	#[Session(key: 'devdock.board.query')]
	public string $query = '';

	#[Session(key: 'devdock.board.attention')]
	public string $attention = '';

	#[Session(key: 'devdock.board.role')]
	public string $role = '';

	/** @var array<int, string> */
	#[Session(key: 'devdock.board.columns')]
	public array $shownColumns = [];

	public string $mobileColumn = '';

	#[Url(as: 'card', except: null)]
	public ?string $openCard = null;

	public string $prompt = '';

	public string $promptSession = '';

	public bool $addingTodo = false;

	public string $todoTitle = '';

	public string $todoNote = '';

	public string $todoProject = '';

	public function mount(): void
	{
		// the session already put a choice in here when you were here before
		if ($this->shownColumns === []) {
			$this->shownColumns = (array) config('devdock.board.columns');
		}

		$this->mobileColumn = $this->shownColumns[0] ?? BoardColumnEnum::TO_DO->value;

		// a link with ?card=... opens that panel at once
		if ($this->openCard !== null) {
			$this->replaceDrawer('card', $this->openCard);
		}
	}

	public function open(string $card): void
	{
		$this->openCard = $card;
		$this->prompt = '';
		$this->promptSession = '';
		$this->replaceDrawer('card', $card);
	}

	/**
	 * Closing clears the url too, so a reload does not open a panel you just
	 * closed again.
	 */
	public function closeDrawer(): void
	{
		$this->openCard = null;
		$this->drawerStack = [];
		$this->renaming = false;
		$this->closeActions();
	}

	public function startTodo(): void
	{
		$this->addingTodo = true;
		$this->todoTitle = '';
		$this->todoNote = '';
		$this->todoProject = '';
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
		]));

		$this->addingTodo = false;
		$this->open($todoEntity->boardKey());
	}

	public function send(): void
	{
		$this->validate([
			'prompt' => ['required', 'min:3'],
		], [
			'prompt.required' => __('Schrijf eerst een prompt.'),
		]);

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);

		if ($cardObject === null) {
			return;
		}

		StartPromptAction::execute(PromptRunObject::from([
			'card' => $cardObject->id,
			'project' => $cardObject->project,
			'branch' => $cardObject->branch,
			'ticket_key' => $cardObject->ticketKey,
			'session_uuid' => $this->promptSession ?: null,
			'prompt' => $this->prompt,
		]));

		$this->prompt = '';
	}

	/**
	 * Writes the open review remarks into the prompt field, so you can read
	 * them over before anything is sent.
	 */
	public function fillPromptFromReview(): void
	{
		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);
		$pullRequestEntity = $this->pullRequestAskingForChanges($cardObject);

		if ($pullRequestEntity === null) {
			return;
		}

		$prompt = BuildReviewPromptAction::execute($pullRequestEntity->pullRequestId);

		if ($prompt !== null) {
			$this->prompt = $prompt;
		}
	}

	public function toggleRole(string $role): void
	{
		$this->role = $this->role === $role ? '' : $role;
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
		$this->shownColumns = array_column(BoardColumnEnum::cases(), 'value');
	}

	public function resetFilters(): void
	{
		$this->query = '';
		$this->role = '';
		$this->attention = '';
		$this->clearProjects();
	}

	public function render(): View
	{
		$cardObjects = collect(BuildBoardAction::execute());
		$visible = $this->visible($cardObjects);

		$columns = collect(BoardColumnEnum::cases())
			->filter(fn (BoardColumnEnum $column) => in_array($column->value, $this->shownColumns, true))
			->map(fn (BoardColumnEnum $column) => [
				'case' => $column,
				'total' => $cardObjects->filter(fn (CardObject $card) => $card->column === $column)->count(),
				'cards' => $visible->filter(fn (CardObject $card) => $card->column === $column)->values(),
			])
			->values();

		if (! $columns->contains(fn (array $column) => $column['case']->value === $this->mobileColumn) && $columns->isNotEmpty()) {
			$this->mobileColumn = $columns->first()['case']->value;
		}

		return view('livewire.board', [
			'columns' => $columns,
			'allColumns' => collect(BoardColumnEnum::cases())->map(fn (BoardColumnEnum $column) => [
				'case' => $column,
				'total' => $cardObjects->filter(fn (CardObject $card) => $card->column === $column)->count(),
			]),
			'projectOptions' => $this->projectOptions($this->countsByProject($cardObjects)),
			'projectTotal' => $cardObjects->count(),
			'signals' => $this->signals($cardObjects),
			'chips' => $this->chips(),
			'allProjectNames' => GetProjectsAction::execute()->pluck('name')->sort()->values(),
			'roles' => [
				'author' => $cardObjects->filter(fn (CardObject $card) => $card->myRole === 'author')->count(),
				'reviewer' => $cardObjects->filter(fn (CardObject $card) => $card->myRole === 'reviewer')->count(),
			],
			'visibleCount' => $visible->count(),
			'totalCount' => $cardObjects->count(),
			'drawer' => $this->drawerContents($cardObjects),
			'claudeAvailable' => resolve(ClaudeProcessRepositoryContract::class)->isAvailable(),
			'filtered' => filled($this->query)
				|| $this->hasProjectFilter()
				|| filled($this->role)
				|| filled($this->attention),
		]);
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
			->when($this->hasProjectFilter(), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => $card->project === null || in_array($card->project, $this->projects, true),
			))
			->when(filled($this->role), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => $card->myRole === $this->role,
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
	 * What lies on top of the stack, with everything that panel needs.
	 *
	 * @param  Collection<int, CardObject>  $cardObjects
	 * @return array<string, mixed>|null
	 */
	private function drawerContents(Collection $cardObjects): ?array
	{
		$top = $this->currentDrawer();

		if ($top === null) {
			return null;
		}

		$previous = $this->previousDrawer();
		$previousLabel = $previous === null ? null : $this->labelFor($previous, $cardObjects);

		if ($top['type'] === 'session') {
			$sessionEntity = $this->sessionByUuid($top['id']);

			return $sessionEntity === null ? null : [
				'type' => 'session',
				'session' => $sessionEntity,
				'projectPath' => $this->projectPathOf($sessionEntity),
				'tab' => $top['tab'],
				'transcript' => $top['tab'] === 'Transcript'
					? collect(GetTranscriptAction::execute($sessionEntity->sessionId))
					: collect(),
				'previous' => $previousLabel,
			];
		}

		$cardObject = $this->cardById($cardObjects, $top['id']);

		$reviewCommentEntities = $this->reviewCommentsOn($cardObject);

		return $cardObject === null ? null : [
			'type' => 'card',
			'card' => $cardObject,
			'reviewComments' => $reviewCommentEntities->reject(
				fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
			)->values(),
			'hasReviewPrompt' => $reviewCommentEntities->contains(
				fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
			),
			'tab' => $top['tab'],
			'run' => GetLatestPromptRunAction::execute($cardObject->id),
			'previous' => $previousLabel,
		];
	}

	/**
	 * Where the back button goes, in words.
	 *
	 * @param  array{type: string, id: string, tab: string}  $entry
	 * @param  Collection<int, CardObject>  $cardObjects
	 */
	private function labelFor(array $entry, Collection $cardObjects): string
	{
		if ($entry['type'] === 'session') {
			return $this->sessionByUuid($entry['id'])?->name() ?? __('Sessie');
		}

		$cardObject = $this->cardById($cardObjects, $entry['id']);

		return $cardObject?->ticketKey ?? $cardObject?->title ?? __('Taak');
	}

	/**
	 * The pull request on this task that is waiting for changes; its remarks
	 * are the ones worth reading.
	 */
	private function pullRequestAskingForChanges(?CardObject $cardObject): ?PullRequestEntity
	{
		return $cardObject?->pullRequests->first(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
		);
	}

	/**
	 * @return Collection<int, ReviewCommentEntity>
	 */
	private function reviewCommentsOn(?CardObject $cardObject): Collection
	{
		$pullRequestEntity = $this->pullRequestAskingForChanges($cardObject);

		if ($pullRequestEntity === null) {
			return collect();
		}

		return collect(GetReviewCommentsAction::execute($pullRequestEntity->pullRequestId));
	}

	/**
	 * @param  Collection<int, CardObject>  $cardObjects
	 */
	private function cardById(Collection $cardObjects, string $id): ?CardObject
	{
		return $cardObjects->first(fn (CardObject $cardObject) => $cardObject->id === $id);
	}

	/**
	 * Where the project of this session stands on disk; that is what you type in
	 * the terminal to carry on there.
	 */
	private function projectPathOf(SessionEntity $sessionEntity): ?string
	{
		if ($sessionEntity->project === null) {
			return null;
		}

		return GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $sessionEntity->project)?->path;
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

	/**
	 * What is switched on now, so you can let it go again.
	 *
	 * @return array<int, array{label: string, filter: string}>
	 */
	private function chips(): array
	{
		return array_values(array_filter([
			filled($this->query) ? ['label' => __('Zoeken: :term', ['term' => $this->query]), 'filter' => 'query'] : null,
			filled($this->role) ? ['label' => $this->role === 'author' ? __('Van mij') : __('Ter review'), 'filter' => 'role'] : null,
			filled($this->attention) ? ['label' => BoardSignalEnum::from($this->attention)->label(), 'filter' => 'attention'] : null,
			...$this->projectChips(),
		]));
	}
}
