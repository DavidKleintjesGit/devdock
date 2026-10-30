<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithDrawerStack;
use App\Livewire\Concerns\WithFilterChips;
use App\Livewire\Concerns\WithProjectFilter;
use App\Livewire\Concerns\WithSessionActions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Actions\BuildTaskPromptAction;
use Src\Board\Actions\CreateTodoAction;
use Src\Board\Actions\DeleteTodoAction;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\BoardSignalEnum;
use Src\Board\Enums\PromptSourceEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\TodoId;
use Src\Board\Objects\TodoObject;
use Src\Packages\Markdown\RenderMarkdown;
use Src\Project\Actions\GetBranchesAction;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Prompt\Actions\GenerateBranchNameAction;
use Src\Prompt\Actions\GenerateCommitMessageAction;
use Src\Prompt\Actions\GetConversationAction;
use Src\Prompt\Actions\GetLatestPromptRunAction;
use Src\Prompt\Actions\ReadReportAction;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Actions\StopPromptAction;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunObject;
use Src\PullRequest\Actions\BuildReviewPromptAction;
use Src\PullRequest\Actions\GetReviewCommentsAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\ReviewCommentEntity;
use Src\Review\Actions\ComposeAgentContextAction;
use Src\Review\Actions\GetRoundsAction;
use Src\Review\Actions\ResolveWorkflowStepAction;
use Src\Review\Actions\RetryLensAction;
use Src\Review\Actions\StartRoundAction;
use Src\Review\Actions\StopRoundAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Session\Actions\GetTranscriptAction;
use Src\Session\Objects\SessionEntity;
use Src\Task\Actions\ActivateAgentAction;
use Src\Task\Actions\ActivateLinkAction;
use Src\Task\Actions\DeleteTaskAction;
use Src\Task\Actions\GetReadableDirectoriesAction;
use Src\Task\Actions\LinkToTaskAction;
use Src\Task\Actions\OpenAgentAction;
use Src\Task\Actions\UnlinkFromTaskAction;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Objects\TaskEntity;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;
use Src\Worktree\Actions\CommitChangesAction;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Actions\GetChangesAction;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Actions\PushBranchAction;
use Src\Worktree\Actions\ReadDiffAction;
use Src\Worktree\Actions\RemoveWorktreeAction;
use Src\Worktree\Actions\RenameToTicketAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\DiffHunkObjectCollection;
use Src\Worktree\Objects\WorktreeEntity;
use Throwable;

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

	#[Url(as: 'tab', except: 'Overzicht')]
	public string $openTab = 'Overzicht';

	public string $prompt = '';

	public string $commitMessage = '';

	public string $commitBody = '';

	public string $linkTicket = '';

	public string $linkBranch = '';

	public string $linkPullRequest = '';

	public string $linkProject = '';

	public string $linkReadableProject = '';

	public string $newBranch = '';

	public string $renameBranch = '';

	public ?string $failure = null;

	public ?string $openDiffFile = null;

	public bool $confirmingDeleteTask = false;

	public bool $linking = false;

	public bool $confirmingPush = false;

	public ?string $committed = null;

	public bool $addingTodo = false;

	public string $todoTitle = '';

	public string $todoNote = '';

	public string $todoProject = '';

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
			$this->shownColumns = (array) config('devdock.board.columns');
		}

		$this->mobileColumn = $this->shownColumns[0] ?? BoardColumnEnum::TO_DO->value;

		// a link with ?card=... opens that panel at once
		if ($this->openCard !== null) {
			$cardObject = $this->cardById(collect(BuildBoardAction::execute()), $this->openCard);

			$this->commitMessage = $this->suggestedCommitMessage($this->openCard);
			$this->newBranch = $this->branchSuggestion($cardObject);
			$this->renameBranch = (string) $this->renameSuggestion($cardObject);
			$this->replaceDrawer('card', $this->openCard, $this->openTab);
		}
	}

	public function open(string $card): void
	{
		$this->openCard = $card;
		$this->prompt = '';
		$this->confirmingPush = false;
		$this->confirmingDeleteTask = false;
		$this->failure = null;
		$this->openDiffFile = null;
		$this->committed = null;
		$this->commitMessage = $this->suggestedCommitMessage($card);
		$this->commitBody = '';
		$this->openTab = 'Overzicht';

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), $card);

		$this->newBranch = $this->branchSuggestion($cardObject);
		$this->renameBranch = (string) $this->renameSuggestion($cardObject);
		$this->replaceDrawer('card', $card);
	}

	/**
	 * Closing clears the url too, so a reload does not open a panel you just
	 * closed again.
	 */
	public function closeDrawer(): void
	{
		$this->openCard = null;
		$this->openTab = 'Overzicht';
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
		$this->failure = null;

		$this->validate([
			'prompt' => ['required', 'min:3'],
		], [
			'prompt.required' => __('Schrijf eerst een prompt.'),
		]);

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);

		if ($cardObject === null) {
			return;
		}

		// without a project there is no folder to work in, and an agent without a
		// folder is an agent that cannot be reached again
		if ($cardObject->project === null) {
			$this->addError('prompt', __('Er ontbreekt nog iets; kijk op de Workflow-tab wat.'));

			return;
		}

		// without a branch the round would land in the checkout itself, next to
		// everything else that is open there
		if ($cardObject->branch === null) {
			$this->addError('prompt', __('Er ontbreekt nog iets; kijk op de Workflow-tab wat.'));

			return;
		}

		StartPromptAction::execute(PromptRunObject::from([
			'card' => $cardObject->id,
			'project' => $cardObject->project,
			'branch' => $cardObject->branch,
			'ticket_key' => $cardObject->ticketKey,
			'prompt' => $this->prompt,
		]));

		$this->prompt = '';
	}

	/**
	 * Hands this task a fresh agent. The one that was in charge stays on the
	 * task, so you can always read back what it did.
	 */
	/**
	 * Hands this delivery to the lenses. Only the ones whose paths are touched
	 * are called; the round holds for the commit it runs on.
	 */
	public function clearFailure(): void
	{
		$this->failure = null;
	}

	public function startRound(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);
		$worktreeEntity = $this->worktreeFor($cardObject);

		// never the checkout itself: a round judges what this task delivered,
		// not everything that happens to be open there
		if ($cardObject === null || $worktreeEntity === null) {
			$this->failure = __('Zonder werkkopie is er niets om de lenzen over te laten gaan.');

			return;
		}

		if (StartRoundAction::execute($cardObject->id, $worktreeEntity->path) === null) {
			$this->failure = __('Er ging geen ronde open: er staat niets gewijzigd, of het maximum aantal rondes is bereikt.');
		}
	}

	public function stopRound(): void
	{
		$reviewRoundEntity = $this->latestRound((string) $this->openCard);

		if ($reviewRoundEntity?->isRunning()) {
			StopRoundAction::execute($reviewRoundEntity->reviewRoundId);
		}
	}

	/**
	 * Laat zien wat deze branch met één bestand deed. Nog een keer klikken
	 * klapt het weer dicht.
	 */
	public function openDiff(string $file): void
	{
		$this->failure = null;
		$this->openDiffFile = $file;
	}

	public function closeDiff(): void
	{
		$this->openDiffFile = null;
	}

	/**
	 * Sends one lens out again, for a lens that fell over rather than judged.
	 */
	public function retryLens(int $lens): void
	{
		$this->failure = null;

		$reviewRoundEntity = $this->latestRound((string) $this->openCard);

		if ($reviewRoundEntity === null) {
			$this->failure = __('Deze ronde bestaat niet meer.');

			return;
		}

		RetryLensAction::execute($reviewRoundEntity->reviewRoundId, new LensId(id: $lens));
	}

	/**
	 * Corrects a branch that does not carry the ticket number. The working
	 * copy and the pinned tie move with it, so nothing is left behind under
	 * the old name.
	 */
	public function renameToTicket(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);
		$to = trim($this->renameBranch);

		if ($cardObject?->branch === null) {
			$this->failure = __('Er is nog geen branch om te hernoemen.');

			return;
		}

		if ($to === '') {
			$this->failure = __('Vul de nieuwe naam in.');

			return;
		}

		if ($to === $cardObject->branch) {
			$this->failure = __('Die naam heeft de branch al.');

			return;
		}

		try {
			RenameToTicketAction::execute($cardObject->id, $cardObject->branch, $to);
		} catch (Throwable $failure) {
			$this->failure = $failure->getMessage();
		}
	}

	/**
	 * Opens the agent without sending anything. It is its own step: a task
	 * gets its agent before there is a working copy to put it in.
	 */
	public function openAgent(): void
	{
		if ($this->openCard !== null) {
			OpenAgentAction::execute($this->openCard, $this->workingDirectory());
		}
	}

	/**
	 * Breaks off the round that is running. What the agent already changed in
	 * the working copy stays; only the process stops.
	 */
	public function stopPrompt(): void
	{
		if ($this->openCard !== null) {
			StopPromptAction::execute($this->openCard);
		}
	}

	public function replaceAgent(): void
	{
		if ($this->openCard !== null) {
			OpenAgentAction::execute($this->openCard, $this->workingDirectory());
		}
	}

	/**
	 * Puts an earlier agent of this task back in charge.
	 */
	public function useAgent(string $sessionUuid): void
	{
		if ($this->openCard !== null) {
			ActivateAgentAction::execute($this->openCard, $sessionUuid);
		}
	}

	/**
	 * Settles which of the pinned ties of a kind this task works with.
	 */
	public function activate(string $kind, string $value): void
	{
		if ($this->openCard !== null) {
			ActivateLinkAction::execute($this->openCard, TaskLinkKindEnum::from($kind), $value);
		}
	}

	/**
	 * Takes the commit message from the closing report, so what the round
	 * already wrote does not have to be asked for a second time.
	 */
	public function useReportedCommitMessage(): void
	{
		$this->failure = null;

		$commitMessageObject = ReadReportAction::execute(
			GetLatestPromptRunAction::execute((string) $this->openCard)?->output,
		)?->commitMessage;

		if ($commitMessageObject === null) {
			$this->failure = __('In het laatste antwoord van de agent staat geen commitbericht.');

			return;
		}

		$this->commitMessage = $commitMessageObject->title;
		$this->commitBody = $commitMessageObject->body;
	}

	/**
	 * Opens Claude Code in a window of its own, in the folder this task works
	 * in and on its working session, so you can carry on by hand.
	 */
	public function openInClaude(): void
	{
		$this->failure = null;

		$directory = $this->workingDirectory();

		if ($directory === null) {
			$this->failure = __('Er is nog geen map om Claude in te openen. Maak eerst de werkkopie.');

			return;
		}

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);

		resolve(ClaudeProcessRepositoryContract::class)->openInTerminal(
			$directory,
			$cardObject?->agent?->sessionUuid,
			GetReadableDirectoriesAction::execute((string) $this->openCard),
		);
	}

	/**
	 * Lays down the working copy this task works in. Without a branch there is
	 * nothing to check out, so one is cut here under the name you gave.
	 */
	public function createWorktree(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);

		if ($cardObject === null) {
			$this->failure = __('Deze kaart is niet meer op het bord te vinden. Ververs even.');

			return;
		}

		if ($cardObject->project === null) {
			$this->failure = __('Zonder project weet ik niet in welke checkout de werkkopie moet komen.');

			return;
		}

		$branch = trim($this->newBranch) ?: $cardObject->branch;

		if ($branch === null) {
			$this->failure = __('Deze taak heeft nog geen branch. Kies er een hierboven of vul een nieuwe naam in.');

			return;
		}

		try {
			EnsureWorktreeAction::execute($cardObject->project, $branch, $cardObject->id);
		} catch (Throwable $failure) {
			$this->failure = $failure->getMessage();

			return;
		}

		// the branch it was cut on is what this task works on from here
		LinkToTaskAction::execute($cardObject->id, TaskLinkKindEnum::BRANCH, $branch);
		ActivateLinkAction::execute($cardObject->id, TaskLinkKindEnum::BRANCH, $branch);

		$this->newBranch = '';
	}

	/**
	 * Takes the working copy off disk. The agent was opened in that folder, so
	 * it cannot be carried on afterwards.
	 */
	public function removeWorktree(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);
		$worktreeEntity = $this->worktreeFor($cardObject);

		if ($cardObject === null || $worktreeEntity === null) {
			$this->failure = __('Er is geen werkkopie om op te ruimen.');

			return;
		}

		// the agent lives in this folder, so it goes only once the work is merged
		$step = ResolveWorkflowStepAction::execute($cardObject, $this->latestRound($cardObject->id));

		if ($step !== WorkflowStepEnum::MERGED) {
			$this->failure = __('De werkkopie gaat pas weg als de pull request gemerged is.');

			return;
		}

		RemoveWorktreeAction::execute($worktreeEntity->worktreeId);
	}

	public function confirmDeleteTask(): void
	{
		$this->confirmingDeleteTask = true;
	}

	/**
	 * Takes this task off the board. A to-do goes altogether; a task that
	 * stands on a ticket or a pull request only loses what was pinned to it.
	 */
	public function deleteTask(): void
	{
		$this->failure = null;

		if (! $this->confirmingDeleteTask || $this->openCard === null) {
			$this->confirmingDeleteTask = true;

			return;
		}

		$card = $this->openCard;

		DeleteTaskAction::execute($card);
		resolve(ReviewRoundRepositoryContract::class)->deleteForCard($card);

		if (str_starts_with($card, 'todo:')) {
			DeleteTodoAction::execute(new TodoId(id: (int) substr($card, 5)));
		}

		$this->confirmingDeleteTask = false;
		$this->closeDrawer();
	}

	public function toggleLinking(): void
	{
		$this->linking = ! $this->linking;
	}

	/**
	 * Pins a tie to this task. From here on the board keeps them together
	 * instead of working it out from names.
	 */
	public function link(string $kind): void
	{
		$linkKind = TaskLinkKindEnum::from($kind);
		$value = trim(match ($linkKind) {
			TaskLinkKindEnum::TICKET => $this->linkTicket,
			TaskLinkKindEnum::BRANCH => $this->linkBranch,
			TaskLinkKindEnum::PULL_REQUEST => $this->linkPullRequest,
			TaskLinkKindEnum::PROJECT => $this->linkProject,
			TaskLinkKindEnum::READABLE_PROJECT => $this->linkReadableProject,
		});

		if ($value === '') {
			return;
		}

		LinkToTaskAction::execute((string) $this->openCard, $linkKind, $value);

		$this->linkTicket = '';
		$this->linkBranch = '';
		$this->linkPullRequest = '';
		$this->linkProject = '';
		$this->linkReadableProject = '';
	}

	public function unlink(string $kind, string $value): void
	{
		UnlinkFromTaskAction::execute((string) $this->openCard, TaskLinkKindEnum::from($kind), $value);
	}

	/**
	 * Builds a first prompt from what the task already carries, so you start
	 * from something instead of from an empty field.
	 */
	public function fillPromptFrom(string $source): void
	{
		$prompt = BuildTaskPromptAction::execute((string) $this->openCard, PromptSourceEnum::from($source));

		if ($prompt !== null) {
			$this->prompt = $prompt;
		}
	}

	/**
	 * Writes the open review remarks into the prompt field, so you can read
	 * them over before anything is sent.
	 */
	public function fillPromptFromReview(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);
		$pullRequestIds = $this->pullRequestsAskingForChanges($cardObject)
			->map(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->pullRequestId)
			->all();

		if ($pullRequestIds === []) {
			$this->failure = __('Er staan geen gevraagde wijzigingen klaar op een pull request van deze taak.');

			return;
		}

		$prompt = BuildReviewPromptAction::execute($pullRequestIds);

		if ($prompt !== null) {
			$this->prompt = $prompt;
		}
	}

	/**
	 * Has the session that made the changes write the commit message. It
	 * knows what it did; the board only sees file names.
	 */
	/**
	 * Lets the agent think of a branch name, for a task whose ticket number
	 * does not already decide it.
	 */
	public function suggestBranchName(): void
	{
		$this->failure = null;

		$branch = GenerateBranchNameAction::execute((string) $this->openCard);

		if ($branch === null) {
			$this->failure = __('De agent kon geen branchnaam voorstellen.');

			return;
		}

		$this->newBranch = $branch;
	}

	public function suggestCommitMessage(): void
	{
		$this->failure = null;

		$commitMessageObject = GenerateCommitMessageAction::execute((string) $this->openCard);

		if ($commitMessageObject === null) {
			$this->committed = __('De sessie kon geen bericht schrijven.');

			return;
		}

		$this->commitMessage = $commitMessageObject->title;
		$this->commitBody = $commitMessageObject->body;
		$this->committed = null;
	}

	/**
	 * Writes what is standing in the working copy down as one commit. It
	 * stays on the machine until you push it yourself.
	 */
	public function commitChanges(): void
	{
		$this->failure = null;

		$this->validate(['commitMessage' => ['required', 'min:5']], [
			'commitMessage.required' => __('Schrijf eerst een commitbericht.'),
			'commitMessage.min' => __('Schrijf eerst een commitbericht.'),
		]);

		$directory = $this->workingDirectory();

		if ($directory === null) {
			$this->failure = __('Er is geen werkkopie om in te committen.');

			return;
		}

		$this->committed = CommitChangesAction::execute($directory, $this->commitMessage, $this->commitBody)
			? __('Vastgelegd in een commit.')
			: __('Committen lukte niet.');

		$this->commitMessage = $this->suggestedCommitMessage((string) $this->openCard);
		$this->commitBody = '';
	}

	/**
	 * Pushing is the step that leaves the machine, so it takes a second
	 * press.
	 */
	public function pushBranch(): void
	{
		$this->failure = null;

		if (! $this->confirmingPush) {
			$this->confirmingPush = true;

			return;
		}

		$directory = $this->workingDirectory();
		$this->confirmingPush = false;

		if ($directory === null) {
			return;
		}

		$this->committed = PushBranchAction::execute($directory)
			? __('Gepusht naar origin.')
			: __('Pushen lukte niet.');
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
			// filter je op een project, dan hoort een taak zonder project er niet
			// bij: die hoort nergens bij
			->when($this->hasProjectFilter(), fn (Collection $all) => $all->filter(
				fn (CardObject $card) => in_array($card->project, $this->projects, true),
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

		if ($cardObject === null) {
			return null;
		}

		$tab = $top['tab'];
		$taskEntity = resolve(TaskRepositoryContract::class)->getByKey($cardObject->id);
		$reviewCommentEntities = $this->reviewCommentsOn($cardObject);
		$promptRunEntity = GetLatestPromptRunAction::execute($cardObject->id);
		$ticketEntity = $this->ticketFor($cardObject);
		$latestRound = $this->latestRound($cardObject->id);
		$changes = $tab === 'Worktree' ? $this->changesFor($cardObject) : collect();
		$step = ResolveWorkflowStepAction::execute($cardObject, $latestRound);
		$diffHunks = ReadDiffAction::execute($this->diffFor($cardObject));
		$pullRequests = $cardObject->pullRequests
			->map(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->repository . '#' . $pullRequestEntity->number)
			->all();

		return [
			'type' => 'card',
			'card' => $cardObject,
			'task' => $taskEntity,
			'tab' => $tab,
			'previous' => $previousLabel,
			'run' => $promptRunEntity,
			'lastReport' => ReadReportAction::execute($promptRunEntity?->output),
			'links' => $this->activeLinks($cardObject),
			'rounds' => $tab === 'Workflow' ? collect(GetRoundsAction::execute($cardObject->id))->reverse()->values() : collect(),
			'agentTurns' => $tab === 'Workflow'
				? resolve(PromptRunRepositoryContract::class)->getByReviewRounds($cardObject->id)
				: [],
			'branchFiles' => $tab === 'Worktree' ? $this->branchFiles($cardObject) : [],
			'openDiffFile' => $this->openDiffFile,
			'diffFiles' => $this->openDiffFile === null ? [] : $this->branchFiles($cardObject),
			'diffHunks' => $diffHunks,
			'diffAdded' => $this->countLines($diffHunks, 'add'),
			'diffRemoved' => $this->countLines($diffHunks, 'del'),
			'latestRound' => $latestRound,
			'step' => $step,
			'steps' => WorkflowStepEnum::cases(),
			'buildSteps' => $tab === 'Workflow' ? $this->buildSteps($cardObject, $ticketEntity) : [],
			'renameTo' => $this->renameSuggestion($cardObject),
			'canDeliver' => $tab === 'Workflow' ? $this->canDeliver($cardObject) : false,
			'maxRounds' => (int) config('devdock.review.max_rounds'),
			'agentContext' => $tab === 'Workflow'
				? ComposeAgentContextAction::execute($cardObject->id, $this->worktreeFor($cardObject)?->path)
				: null,
			'contextPath' => resolve(AgentContextRepositoryContract::class)->relativePath(),
			'agents' => $taskEntity?->agents,
			'conversation' => $tab === 'Agent' ? GetConversationAction::execute($cardObject->id) : [],
			'promptSources' => $tab === 'Agent' ? $this->promptSources($cardObject, $reviewCommentEntities) : [],
			'worktree' => $this->worktreeFor($cardObject),
			'changes' => $changes,
			'uncommittedSummary' => $changes->isEmpty()
				? __('niets open')
				: trans_choice(':count bestand|:count bestanden', $changes->count(), ['count' => $changes->count()])
. sprintf(' · +%d −%d', $changes->sum('added'), $changes->sum('removed')),
			'branchSummary' => $this->branchSummary($cardObject, $tab),
			'pushLabel' => $this->pushLabel($cardObject),
			'openOnWorktree' => $this->openOnWorktree($this->worktreeFor($cardObject), $step, $changes),
			'readyToCommit' => $tab === 'Worktree'
				&& $changes->isNotEmpty()
				&& $latestRound?->status === RoundStatusEnum::PASSED,
			'branchRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::BRANCH, array_filter([$cardObject->branch]), $cardObject->branch),
			'readableProjects' => $taskEntity?->valuesFor(TaskLinkKindEnum::READABLE_PROJECT) ?? [],
			'projectChoices' => $tab === 'Worktree' ? GetProjectsAction::execute()->pluck('name')->sort()->values() : collect(),
			'branchChoices' => $tab === 'Worktree' && $cardObject->project !== null
				? GetBranchesAction::execute($cardObject->project)
				: [],
			'ticket' => $ticketEntity,
			'description' => $cardObject->description,
			'ticketRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::TICKET, array_filter([$cardObject->ticketKey]), $cardObject->ticketKey),
			'activePullRequestUrl' => $cardObject->pullRequests->first(
				fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->repository . '#' . $pullRequestEntity->number === $cardObject->activePullRequest,
			)?->url,
			'pullRequestRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::PULL_REQUEST, $pullRequests, $cardObject->activePullRequest),
			'reviewComments' => $reviewCommentEntities->reject(
				fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
			)->values(),
			'hasReviewPrompt' => $reviewCommentEntities->contains(
				fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
			),
			'report' => $promptRunEntity?->output === null
				? null
				: resolve(RenderMarkdown::class)->toHtml($promptRunEntity->output),
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
	 * What this task would commit as, so the field is filled before you get
	 * there.
	 */
	private function suggestedCommitMessage(string $card): string
	{
		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), $card);

		return $cardObject?->suggestedCommitMessage() ?? '';
	}

	/**
	 * The folder the work of this task stands in: its own working copy when
	 * there is one, otherwise wherever the last round ran.
	 */
	private function workingDirectory(): ?string
	{
		$cardObject = $this->cardById(collect(BuildBoardAction::execute()), (string) $this->openCard);

		if ($cardObject === null) {
			return null;
		}

		$worktreeEntity = $this->worktreeFor($cardObject);

		return $cardObject->agent?->directory
			?? $worktreeEntity?->path
			?? GetLatestPromptRunAction::execute($cardObject->id)?->directory
			?? ($cardObject->project === null ? null : config('devdock.paths.sites') . '/' . $cardObject->project);
	}

	/**
	 * @return Collection<int, ChangeObject>
	 */
	private function changesFor(?CardObject $cardObject): Collection
	{
		$worktreeEntity = $this->worktreeFor($cardObject);
		$directory = $worktreeEntity?->path
			?? GetLatestPromptRunAction::execute((string) $cardObject?->id)?->directory
			?? ($cardObject?->project === null ? null : config('devdock.paths.sites') . '/' . $cardObject->project);

		return $directory === null ? collect() : collect(GetChangesAction::execute($directory));
	}

	/**
	 * The working copy this task runs in, once it has one.
	 */
	private function worktreeFor(?CardObject $cardObject): ?WorktreeEntity
	{
		if ($cardObject?->project === null || $cardObject->branch === null) {
			return null;
		}

		return GetWorktreesAction::execute()->first(
			fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->project === $cardObject->project
				&& $worktreeEntity->branch === $cardObject->branch,
		);
	}

	/**
	 * What this task works with right now, one of each kind. Swapping and
	 * unlinking happens on the tab of that part.
	 *
	 * @return array<int, array{kind: TaskLinkKindEnum, value: string}>
	 */
	private function activeLinks(CardObject $cardObject): array
	{
		$values = [
			TaskLinkKindEnum::TICKET->value => $cardObject->ticketKey,
			TaskLinkKindEnum::PROJECT->value => $cardObject->project,
			TaskLinkKindEnum::BRANCH->value => $cardObject->branch,
			TaskLinkKindEnum::PULL_REQUEST->value => $cardObject->activePullRequest,
		];

		$links = [];

		foreach (array_filter($values) as $kind => $value) {
			$links[] = ['kind' => TaskLinkKindEnum::from($kind), 'value' => $value];
		}

		return $links;
	}

	/**
	 * Everything of one kind that hangs on this task, pinned by hand or worked
	 * out from names, with the one it works with marked.
	 *
	 * @param  array<int, string>  $derived
	 * @return array<int, array{value: string, isActive: bool, isPinned: bool}>
	 */
	private function linkRows(?TaskEntity $taskEntity, TaskLinkKindEnum $kind, array $derived, ?string $active): array
	{
		$pinned = $taskEntity?->valuesFor($kind) ?? [];
		$values = array_values(array_unique([...$pinned, ...array_values($derived)]));

		return array_map(fn (string $value) => [
			'value' => $value,
			'isActive' => $value === $active,
			'isPinned' => in_array($value, $pinned, true),
		], $values);
	}

	/**
	 * What has to stand before an agent can build. In this order, because
	 * everything is named after the ticket number: the number first, then the
	 * task, then the agent, and only then the working copy.
	 *
	 * @return array<int, array{key: string, label: string, detail: string, isDone: bool, action: ?string, button: ?string}>
	 */
	private function buildSteps(CardObject $cardObject, ?TicketEntity $ticketEntity): array
	{
		$worktreeEntity = $this->worktreeFor($cardObject);

		return [
			[
				'key' => 'ticket',
				'label' => __('Ticketnummer'),
				'detail' => $cardObject->ticketKey ?? __('nog geen nummer; alles heet hiernaar'),
				'isDone' => $cardObject->ticketKey !== null,
				'action' => $cardObject->ticketKey === null ? "setDrawerTab('Jira')" : null,
				'button' => $cardObject->ticketKey === null ? __('Nummer toewijzen') : null,
			],
			[
				'key' => 'task',
				'label' => __('Taak'),
				'detail' => $ticketEntity !== null ? __('op het bord en in Jira') : __('op het bord, niet in Jira'),
				'isDone' => true,
				'action' => null,
				'button' => null,
			],
			[
				'key' => 'agent',
				'label' => __('Agent'),
				'detail' => $cardObject->agent?->name ?? __('nog geen sessie geopend'),
				'isDone' => $cardObject->agent !== null,
				'action' => $cardObject->agent === null ? 'openAgent' : null,
				'button' => $cardObject->agent === null ? __('Agent openen') : null,
			],
			[
				'key' => 'worktree',
				'label' => __('Werkkopie'),
				'detail' => $worktreeEntity?->path ?? __('nog geen eigen map'),
				'isDone' => $worktreeEntity !== null,
				'action' => $worktreeEntity === null ? "setDrawerTab('Worktree')" : null,
				'button' => $worktreeEntity === null ? __('Werkkopie maken') : null,
			],
			[
				'key' => 'build',
				'label' => __('Bouwen'),
				'detail' => $cardObject->agent !== null && $worktreeEntity !== null
					? __('alles staat, de agent kan beginnen')
					: __('pas als het bovenstaande staat'),
				'isDone' => false,
				'action' => $cardObject->agent !== null && $worktreeEntity !== null ? "setDrawerTab('Agent')" : null,
				'button' => $cardObject->agent !== null && $worktreeEntity !== null ? __('Naar de agent') : null,
			],
		];
	}

	/**
	 * Whether there is something to hand over: a working copy of its own with
	 * work standing in it. Without that a round would judge thin air, or worse,
	 * the checkout itself.
	 */
	private function canDeliver(CardObject $cardObject): bool
	{
		$worktreeEntity = $this->worktreeFor($cardObject);

		return $worktreeEntity !== null
			&& resolve(WorktreeRepositoryContract::class)->getChangedFiles($worktreeEntity->path) !== [];
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
	 * Elk bestand dat deze branch veranderde tegenover de hoofdlijn. Dat is
	 * precies wat de lenzen beoordeelden.
	 *
	 * @return array<int, string>
	 */
	private function branchFiles(CardObject $cardObject): array
	{
		$worktreeEntity = $this->worktreeFor($cardObject);

		return $worktreeEntity === null
			? []
			: resolve(WorktreeRepositoryContract::class)->getChangedFiles($worktreeEntity->path);
	}

	/**
	 * Welke blokken op de Worktree-tab openstaan. Dat hangt af van waar de
	 * taak staat: aan het begin zet je de koppelingen goed, daarna kijk je
	 * alleen nog naar wat er veranderd is, en na de merge ruim je op.
	 *
	 * @return array<string, bool>
	 */
	private function openOnWorktree(
		?WorktreeEntity $worktreeEntity,
		WorkflowStepEnum $step,
		Collection $changes,
	): array {
		$building = $step === WorkflowStepEnum::BUILDING;
		$merged = $step === WorkflowStepEnum::MERGED;

		return [
			'links' => $building && $worktreeEntity === null,
			'worktree' => $merged || $worktreeEntity === null,
			'uncommitted' => ! $merged && $changes->isNotEmpty(),
			'branch' => ! $merged && ! $building && $changes->isEmpty(),
			'message' => ! $merged && $changes->isNotEmpty(),
		];
	}

	/**
	 * Hoeveel regels er bij kwamen of af gingen, over alle stukken heen.
	 */
	private function countLines(DiffHunkObjectCollection $diffHunks, string $kind): int
	{
		$total = 0;

		foreach ($diffHunks as $diffHunkObject) {
			foreach ($diffHunkObject->lines as $diffLineObject) {
				$total += $diffLineObject->kind === $kind ? 1 : 0;
			}
		}

		return $total;
	}

	/**
	 * Wat er op de branch staat, in één regel voor de kop van het blok.
	 */
	private function branchSummary(CardObject $cardObject, string $tab): string
	{
		if ($tab !== 'Worktree') {
			return '';
		}

		$files = count($this->branchFiles($cardObject));

		return $files === 0
			? __('nog niets')
			: trans_choice(
				':count bestand sinds de hoofdlijn|:count bestanden sinds de hoofdlijn',
				$files,
				['count' => $files],
			);
	}

	/**
	 * Wat er op de pushknop staat: zonder commits is er niets te sturen.
	 */
	private function pushLabel(CardObject $cardObject): string
	{
		if ($this->confirmingPush) {
			return __('Zeker weten? Pushen');
		}

		$ahead = $this->worktreeFor($cardObject)?->ahead ?? 0;

		return $ahead === 0 ? __('Pushen') : __('Pushen (:count)', ['count' => $ahead]);
	}

	/**
	 * De wijziging van het bestand dat openstaat.
	 */
	private function diffFor(CardObject $cardObject): ?string
	{
		$worktreeEntity = $this->worktreeFor($cardObject);

		return $this->openDiffFile === null || $worktreeEntity === null
			? null
			: resolve(WorktreeRepositoryContract::class)->getDiff($worktreeEntity->path, $this->openDiffFile);
	}

	private function branchSuggestion(?CardObject $cardObject): string
	{
		if ($cardObject === null || $cardObject->branch !== null) {
			return '';
		}

		if ($cardObject->ticketKey !== null) {
			return 'feature/' . $cardObject->ticketKey;
		}

		$slug = Str::slug(Str::limit($cardObject->title, 48, ''));

		return $slug === '' ? '' : 'feature/' . $slug;
	}

	private function renameSuggestion(?CardObject $cardObject): ?string
	{
		if ($cardObject?->branch === null || $cardObject->ticketKey === null) {
			return null;
		}

		return str_contains($cardObject->branch, $cardObject->ticketKey)
			? null
			: 'feature/' . $cardObject->ticketKey;
	}

	/**
	 * The round a task stands on, which is its last one.
	 */
	private function latestRound(string $card): ?ReviewRoundEntity
	{
		return collect(GetRoundsAction::execute($card))->last();
	}

	/**
	 * The ticket behind this task, with everything Jira knows about it.
	 */
	private function ticketFor(CardObject $cardObject): ?TicketEntity
	{
		if ($cardObject->ticketKey === null) {
			return null;
		}

		return collect(GetTicketsAction::execute())->first(
			fn (TicketEntity $ticketEntity) => $ticketEntity->key === $cardObject->ticketKey,
		);
	}

	/**
	 * Which sources this task can build a first prompt from.
	 *
	 * @param  Collection<int, ReviewCommentEntity>  $reviewCommentEntities
	 * @return array<int, array{case: PromptSourceEnum, count: ?int}>
	 */
	private function promptSources(?CardObject $cardObject, Collection $reviewCommentEntities): array
	{
		if ($cardObject === null) {
			return [];
		}

		$findings = $reviewCommentEntities->reject(
			fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
		);

		$sources = [];

		if ($cardObject->ticketKey !== null) {
			$sources[] = ['case' => PromptSourceEnum::TICKET, 'count' => null];
		}

		if (filled($cardObject->description)) {
			$sources[] = ['case' => PromptSourceEnum::NOTE, 'count' => null];
		}

		if ($findings->isNotEmpty()) {
			$sources[] = ['case' => PromptSourceEnum::REVIEW, 'count' => $findings->count()];
		}

		$open = $this->latestRound($cardObject->id)?->openFindings() ?? 0;

		if ($open > 0) {
			$sources[] = ['case' => PromptSourceEnum::ROUND, 'count' => $open];
		}

		return $sources;
	}

	/**
	 * The pull requests on this task that are waiting for changes; their
	 * remarks are the ones worth reading.
	 *
	 * @return Collection<int, PullRequestEntity>
	 */
	private function pullRequestsAskingForChanges(?CardObject $cardObject): Collection
	{
		if ($cardObject === null) {
			return collect();
		}

		return $cardObject->pullRequests
			->filter(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested())
			->values();
	}

	/**
	 * @return Collection<int, ReviewCommentEntity>
	 */
	private function reviewCommentsOn(?CardObject $cardObject): Collection
	{
		return $this->pullRequestsAskingForChanges($cardObject)
			->flatMap(fn (PullRequestEntity $pullRequestEntity) => collect(
				GetReviewCommentsAction::execute($pullRequestEntity->pullRequestId),
			))
			->values();
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
