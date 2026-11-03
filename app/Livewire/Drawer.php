<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithContext;
use App\Livewire\Concerns\WithDrawerStack;
use App\Livewire\Concerns\WithSessionActions;
use App\Livewire\Concerns\WithSetup;
use App\Livewire\Concerns\WithWorkingCopy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Access\Actions\GetAccessPolicyAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Board\Actions\BuildTaskPromptAction;
use Src\Board\Actions\DeleteTodoAction;
use Src\Board\Actions\GetBoardAction;
use Src\Board\Actions\PlanTodoLaterAction;
use Src\Board\Contracts\CardViewRepositoryContract;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\CardStatusEnum;
use Src\Board\Enums\PromptSourceEnum;
use Src\Board\Enums\UnreadSourceEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\TodoId;
use Src\Fetch\Actions\GetLatestFetchRunAction;
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
use Src\Review\Actions\GetLoopStatusAction;
use Src\Review\Actions\GetRoundTurnsAction;
use Src\Review\Actions\GetRoundsAction;
use Src\Review\Actions\GetWorkflowSettingAction;
use Src\Review\Actions\ResolveWorkflowStepAction;
use Src\Review\Actions\RetryLensAction;
use Src\Review\Actions\StartRoundAction;
use Src\Review\Actions\StopRoundAction;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\WorkflowStepEnum;
use Src\Review\Exceptions\RoundNotStartedException;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;
use Src\Session\Actions\GetSessionTraitsAction;
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
use Src\Worktree\Actions\BuildFileTreeAction;
use Src\Worktree\Actions\CreateWorktreeDatabaseAction;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Actions\GetChangesAction;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Actions\ReadDiffAction;
use Src\Worktree\Actions\RemoveWorktreeAction;
use Src\Worktree\Actions\RenameToTicketAction;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\DiffViewEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\DiffHunkObjectCollection;
use Src\Worktree\Objects\FileTreeNodeObject;
use Src\Worktree\Objects\WorktreeEntity;
use Throwable;

/**
 * The side panel of the board: a card, and the sessions and findings opened
 * on top of it. It lives apart from the board, so working in a card draws
 * only the panel again and not every column.
 */
class Drawer extends Component
{
	use WithContext;
	use WithDrawerStack {
		setDrawerTab as private switchDrawerTab;
	}
	use WithSessionActions;
	use WithSetup;
	use WithWorkingCopy;

	/**
	 * A file with more changed lines than this opens closed in the code reader.
	 */
	private const LARGE_DIFF_LINES = 600;


	/**
	 * A finished round of fetching brings the panel up to date.
	 */
	protected $listeners = ['fetched' => '$refresh'];

	/**
	 * The board as built in this request; never sent to the browser.
	 *
	 * @var Collection<int, CardObject>|null
	 */
	private ?Collection $cardObjects = null;

	/**
	 * Whether this request wrote anything, which the board then has to show.
	 */
	private bool $hasWritten = false;

	#[Url(as: 'card', except: null)]
	public ?string $openCard = null;

	#[Url(as: 'tab', except: 'Overzicht')]
	public string $openTab = 'Overzicht';

	public string $prompt = '';

	/** @var array<int, string> */
	public array $pickedSources = [];

	public bool $composing = false;

	/**
	 * What the open panel last showed of a running agent or lens round.
	 */
	public string $progressSeen = '';

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

	/** Which changes the Werkkopie tab and the code reader show; a DiffViewEnum value, or null for the default. */
	public ?string $diffView = null;


	public bool $confirmingDeleteTask = false;

	public bool $linking = false;

	public ?string $committed = null;

	public string $newDatabaseMode = 'own';

	/**
	 * Any write to the database can change what the board shows, so it
	 * also clears the board this request already built.
	 */
	public function boot(): void
	{
		DB::listen(function (QueryExecuted $queryExecuted): void {
			if (! str_starts_with(ltrim(strtolower($queryExecuted->sql)), 'select')) {
				$this->cardObjects = null;
				$this->hasWritten = true;
			}
		});
	}

	public function mount(): void
	{
		// a link with ?card=... opens that panel at once
		if ($this->openCard !== null) {
			$cardObject = $this->cardById($this->board(), $this->openCard);

			$this->commitMessage = $this->suggestedCommitMessage($this->openCard);
			$this->newBranch = $this->branchSuggestion($cardObject);
			$this->renameBranch = (string) $this->renameSuggestion($cardObject);
			$this->resetSetup($cardObject);
			$this->replaceDrawer('card', $this->openCard, $this->openTab);
			$this->markTabSeen($this->openCard, $this->openTab);
		}
	}

	/**
	 * The board sends this when a card is clicked; any write the panel makes
	 * goes back the other way.
	 */
	public function dehydrate(): void
	{
		if ($this->hasWritten) {
			$this->dispatch('board-changed')->to(Board::class);
		}
	}

	#[On('open-card')]
	public function open(string $card): void
	{
		$this->openCard = $card;
		$this->prompt = '';
		$this->pickedSources = [];
		$this->composing = false;
		$this->confirmingDeleteTask = false;
		$this->failure = null;
		$this->openDiffFile = null;
		$this->committed = null;
		$this->commitMessage = $this->suggestedCommitMessage($card);
		$this->commitBody = '';
		$this->openTab = 'Overzicht';
		$this->diffView = null;

		$cardObject = $this->cardById($this->board(), $card);

		$this->newBranch = $this->branchSuggestion($cardObject);
		$this->renameBranch = (string) $this->renameSuggestion($cardObject);
		$this->resetSetup($cardObject);
		$this->replaceDrawer('card', $card);
	}

	/**
	 * Closing clears the url too, so a reload does not open a panel you just
	 * closed again.
	 */
	public function closeDrawer(): void
	{
		// what happened on the tab you had open, you saw
		if ($this->openCard !== null) {
			$this->markTabSeen($this->openCard, $this->openTab);
		}

		$this->openCard = null;
		$this->openTab = 'Overzicht';
		$this->drawerStack = [];
		$this->renaming = false;
		$this->closeActions();
	}

	public function send(): void
	{
		$this->failure = null;

		$this->validate([
			'prompt' => ['required', 'min:3'],
		], [
			'prompt.required' => __('Schrijf eerst een prompt.'),
		]);

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

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
	 * One of the answers the agent offered, sent back as your message.
	 */
	public function answer(string $choice): void
	{
		$this->prompt = $choice;

		$this->send();
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

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$worktreeEntity = $this->worktreeFor($cardObject);

		// never the checkout itself: a round judges what this task delivered,
		// not everything that happens to be open there
		if ($cardObject === null || $worktreeEntity === null) {
			$this->failure = __('Zonder werkkopie is er niets om de lenzen over te laten gaan.');

			return;
		}

		try {
			if (StartRoundAction::execute($cardObject->id, $worktreeEntity->path) === null) {
				$this->failure = __('Er ging geen ronde open.');
			}
		} catch (WorktreeNotPossibleException|RoundNotStartedException $refusal) {
			$this->failure = $refusal->getMessage();
		}
	}

	/**
	 * Starts a new series of rounds after the loop stopped at its maximum,
	 * once you looked at what went wrong and had the agent change it.
	 */
	public function restartLoop(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$worktreeEntity = $this->worktreeFor($cardObject);

		if ($cardObject === null || $worktreeEntity === null) {
			$this->failure = __('Zonder werkkopie is er niets om de lenzen over te laten gaan.');

			return;
		}

		try {
			if (StartRoundAction::execute($cardObject->id, $worktreeEntity->path, true) === null) {
				$this->failure = __('Er ging geen ronde open.');
			}
		} catch (WorktreeNotPossibleException|RoundNotStartedException $refusal) {
			$this->failure = $refusal->getMessage();
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
	public function openDiff(string $file, ?string $view = null): void
	{
		$this->failure = null;
		$this->openDiffFile = $file;

		// from the tree the view stays; from a list on the card it is the view of that list
		if ($view !== null && DiffViewEnum::tryFrom($view) !== null) {
			$this->diffView = $view;
		}
	}

	/**
	 * Opens the code reader on the commits no remote holds yet, at their first file.
	 */
	public function openUnpushedDiff(): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$worktreeEntity = $cardObject === null ? null : $this->worktreeFor($cardObject);
		$first = $worktreeEntity === null ? null : collect(resolve(WorktreeRepositoryContract::class)->getChangesFor($worktreeEntity->path, DiffViewEnum::UNPUSHED))->first();

		if ($first !== null) {
			$this->openDiff($first->path, DiffViewEnum::UNPUSHED->value);
		}
	}

	/**
	 * Switches the code reader to other changes. The open file stays when it
	 * is among them; otherwise the first one of the new list opens.
	 */
	public function setDiffView(string $view): void
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$worktreeEntity = $cardObject === null ? null : $this->worktreeFor($cardObject);
		$diffView = DiffViewEnum::tryFrom($view);

		if ($worktreeEntity === null || $diffView === null) {
			return;
		}

		$this->diffView = $diffView->value;

		$paths = collect(resolve(WorktreeRepositoryContract::class)->getChangesFor($worktreeEntity->path, $diffView))->pluck('path');

		if ($this->openDiffFile !== null && $paths->isNotEmpty() && ! $paths->contains($this->openDiffFile)) {
			$this->openDiffFile = (string) $paths->first();
		}
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

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
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

		$promptRunEntity = GetLatestPromptRunAction::execute((string) $this->openCard);
		$commitMessageObject = ReadReportAction::execute($promptRunEntity?->output)?->commitMessage;

		if ($commitMessageObject === null) {
			$this->failure = __('In het laatste antwoord van de agent staat geen commitbericht.');

			return;
		}

		$commitMessageObject = $commitMessageObject->withTicketKey($promptRunEntity?->ticketKey);

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

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

		resolve(ClaudeProcessRepositoryContract::class)->openInTerminal(
			GetAccessPolicyAction::execute(AgentRoleEnum::AGENT),
			$directory,
			$cardObject?->agent?->sessionUuid,
			GetReadableDirectoriesAction::execute((string) $this->openCard),
			$directory . '/' . resolve(AgentContextRepositoryContract::class)->relativePath(),
		);
	}

	/**
	 * Lays down the working copy this task works in. Without a branch there is
	 * nothing to check out, so one is cut here under the name you gave.
	 */
	public function createWorktree(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

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
			$worktreeEntity = EnsureWorktreeAction::execute($cardObject->project, $branch, $cardObject->id);
		} catch (Throwable $failure) {
			$this->failure = $failure->getMessage();

			return;
		}

		// the branch it was cut on is what this task works on from here
		LinkToTaskAction::execute($cardObject->id, TaskLinkKindEnum::BRANCH, $branch);
		ActivateLinkAction::execute($cardObject->id, TaskLinkKindEnum::BRANCH, $branch);

		$this->newBranch = '';

		// sharing is what a fresh copy does by itself; anything else is made now
		if ($this->newDatabaseMode !== 'shared') {
			$this->failure = CreateWorktreeDatabaseAction::execute(
				$worktreeEntity,
				(string) $this->databaseSuggestionFor($cardObject),
				$this->newDatabaseMode === 'own' ? DatabaseFillEnum::COPY : DatabaseFillEnum::EMPTY,
			);
		}
	}

	/**
	 * Takes the working copy off disk. The agent was opened in that folder, so
	 * it cannot be carried on afterwards.
	 */
	public function removeWorktree(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
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

		if (! RemoveWorktreeAction::execute($worktreeEntity->worktreeId)) {
			$this->failure = __('Git weigerde de werkkopie weg te halen: er staat nog werk in dat niet gecommit is.');

			return;
		}

		// de lenzen schreven hun antwoorden buiten de werkkopie weg; die horen bij de taak en gaan nu mee
		resolve(ReviewRoundRepositoryContract::class)->deleteLogsForCard($cardObject->id);
		resolve(PromptRunRepositoryContract::class)->deleteLogsForCard($cardObject->id);
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
	 * Builds a prompt from the parts of the context you ticked, so you start
	 * from something instead of from an empty field. You read it before it
	 * goes anywhere.
	 */
	public function buildPrompt(): void
	{
		$sources = array_values(array_filter(array_map(
			fn (string $source) => PromptSourceEnum::tryFrom($source),
			$this->pickedSources,
		)));

		$prompt = $sources === [] ? null : BuildTaskPromptAction::execute((string) $this->openCard, $sources);

		if ($prompt !== null) {
			$this->prompt = $prompt;
			$this->composing = false;
		}
	}

	/**
	 * The poll while an agent or a lens round runs. The board is only drawn
	 * again when something in that run moved; otherwise the answer is empty.
	 */
	public function followProgress(): void
	{
		$card = (string) $this->openCard;
		$promptRunEntity = GetLatestPromptRunAction::execute($card);
		$reviewRoundEntity = $this->latestRound($card);

		$logPath = $promptRunEntity === null ? null : resolve(PromptRunRepositoryContract::class)->logPath($promptRunEntity->promptRunId);
		clearstatcache();

		$progress = md5((string) json_encode([
			$promptRunEntity?->status,
			$promptRunEntity?->finishedAt,
			// the agent writes its events as they come, so a growing log is progress too
			$logPath !== null && is_file($logPath) ? filesize($logPath) : null,
			$reviewRoundEntity?->status,
			$reviewRoundEntity?->results,
		]));

		if ($progress === $this->progressSeen) {
			$this->skipRender();

			return;
		}

		$this->progressSeen = $progress;
	}

	/**
	 * Opening the composer ticks what most likely matters now: the remarks
	 * and findings waiting on the task, or else everything there is.
	 */
	public function toggleComposing(): void
	{
		$this->composing = ! $this->composing;

		if (! $this->composing || $this->pickedSources !== []) {
			return;
		}

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
		$available = collect($this->promptSources($cardObject, $cardObject === null ? collect() : $this->reviewCommentsOn($cardObject)))
			->map(fn (array $source) => $source['case']);
		$waiting = $available->filter(fn (PromptSourceEnum $source) => in_array($source, [PromptSourceEnum::REVIEW, PromptSourceEnum::ROUND], true));

		$this->pickedSources = ($waiting->isNotEmpty() ? $waiting : $available)
			->map(fn (PromptSourceEnum $source) => $source->value)
			->values()
			->all();
	}

	public function togglePromptSource(string $source): void
	{
		$this->pickedSources = in_array($source, $this->pickedSources, true)
			? array_values(array_diff($this->pickedSources, [$source]))
			: [...$this->pickedSources, $source];
	}

	/**
	 * Writes the open review remarks into the prompt field, so you can read
	 * them over before anything is sent.
	 */
	public function fillPromptFromReview(): void
	{
		$this->failure = null;

		$cardObject = $this->cardById($this->board(), (string) $this->openCard);
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
	 * Opens what one lens found in one round, on top of the card.
	 */
	public function openFinding(int $round, int $lens): void
	{
		$this->openDrawer('finding', $round . ':' . $lens);
	}

	/**
	 * Moves the open to-do between the sprint and the backlog.
	 */
	public function planLater(bool $isLater): void
	{
		if ($this->openCard !== null && str_starts_with($this->openCard, 'todo:')) {
			PlanTodoLaterAction::execute(new TodoId(id: (int) substr($this->openCard, 5)), $isLater);
		}
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

			return $sessionEntity === null
				? null
				: $this->sessionDrawer($sessionEntity, GetSessionTraitsAction::execute()[$sessionEntity->uuid] ?? [], $previousLabel);
		}

		if ($top['type'] === 'finding') {
			return $this->findingContents($top['id'], $previousLabel);
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
		$roundTurns = $tab === 'Review' ? GetRoundTurnsAction::execute($cardObject->id) : [];
		$rounds = $tab === 'Review' ? collect(GetRoundsAction::execute($cardObject->id))->reverse()->values() : collect();
		$worktreeEntity = $this->worktreeFor($cardObject);
		$changes = $tab === 'Werkkopie' && $worktreeEntity !== null ? $this->changesFor($cardObject) : collect();
		// a task with a working copy but without an agent is not set up yet: the panel stays until all three steps are done
		$isSetup = in_array($cardObject->column, [BoardColumnEnum::TO_DO, BoardColumnEnum::LATER], true)
			|| ($cardObject->column === BoardColumnEnum::BUILDING && $cardObject->agent === null && $promptRunEntity === null);
		$diffView = $worktreeEntity === null ? DiffViewEnum::BRANCH : $this->diffViewFor($worktreeEntity);
		$diffTree = $this->openDiffFile === null ? [] : $this->diffTree($cardObject, $diffView);
		$diffFiles = $this->openDiffFile === null || $worktreeEntity === null ? [] : $this->stackedDiffs($worktreeEntity, $diffView, $diffTree);
		$workflowSetting = GetWorkflowSettingAction::execute($cardObject->project);
		$promptSources = $tab === 'Agent' ? $this->promptSources($cardObject, $reviewCommentEntities) : [];
		$pullRequests = $cardObject->pullRequests
			->map(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->repository . '#' . $pullRequestEntity->number)
			->all();

		return [
			'type' => 'card',
			'card' => $cardObject,
			'task' => $taskEntity,
			'tab' => $isSetup ? 'Inrichten' : $tab,
			'isSetup' => $isSetup,
			'previous' => $previousLabel,
			'cardSteps' => $this->cardSteps($cardObject),
			'nextStep' => $this->nextStep($cardObject, $worktreeEntity, $latestRound),
			...($isSetup ? $this->setupData($cardObject, $worktreeEntity) : []),
			'databaseSuggestion' => $worktreeEntity === null && ($isSetup || $tab === 'Werkkopie') ? $this->databaseSuggestionFor($cardObject) : null,
			'run' => $promptRunEntity,
			'lastReport' => ReadReportAction::execute($promptRunEntity?->output),
			'rounds' => $rounds,
			'roundRows' => $rounds->map(fn (ReviewRoundEntity $reviewRoundEntity) => [
				'round' => $reviewRoundEntity,
				'turn' => $roundTurns[(int) $reviewRoundEntity->reviewRoundId->id] ?? null,
			]),
			'loopStatus' => $tab === 'Review'
				? GetLoopStatusAction::execute($latestRound, $latestRound === null ? null : $roundTurns[(int) $latestRound->reviewRoundId->id] ?? null, $workflowSetting->maxRounds)
				: null,
			'isExpired' => $latestRound?->isExpiredBy($worktreeEntity === null ? null : resolve(WorktreeRepositoryContract::class)->getFingerprint($worktreeEntity->path)) ?? false,
			'openDiffFile' => $this->openDiffFile,
			'diffTree' => $diffTree,
			'diffView' => $diffView,
			'diffViews' => $this->openDiffFile === null || $worktreeEntity === null ? [] : $this->diffViewCounts($worktreeEntity),
			'unpushedCount' => $this->openDiffFile === null || $worktreeEntity === null ? 0 : resolve(WorktreeRepositoryContract::class)->getNewCommitCount($worktreeEntity->path),
			'diffFiles' => $diffFiles,
			'diffFingerprints' => collect($diffFiles)->mapWithKeys(fn (array $diffFile) => [$diffFile['change']->path => $diffFile['fingerprint']])->all(),
			'diffAdded' => collect($diffFiles)->sum(fn (array $diffFile) => $diffFile['change']->added),
			'diffRemoved' => collect($diffFiles)->sum(fn (array $diffFile) => $diffFile['change']->removed),
			'openAnchor' => $this->openDiffFile === null ? null : 'df-' . md5($this->openDiffFile),
			'latestRound' => $latestRound,
			'renameTo' => $this->renameSuggestion($cardObject),
			'canDeliver' => $tab === 'Review' ? $this->canDeliver($cardObject) : false,
			'maxRounds' => $workflowSetting->maxRounds,
			'afterRounds' => $workflowSetting->afterRounds,
			'agents' => $taskEntity?->agents,
			'conversation' => $tab === 'Agent' ? GetConversationAction::execute($cardObject->id) : [],
			'promptSources' => $promptSources,
			'promptSourceSummary' => collect($promptSources)->map(fn (array $source) => $source['case']->label())->implode(', '),
			'pickedSourceCount' => count(array_intersect(
				$this->pickedSources,
				collect($promptSources)->map(fn (array $source) => $source['case']->value)->all(),
			)),
			'claudeAvailable' => resolve(ClaudeProcessRepositoryContract::class)->isAvailable(),
			...($tab === 'Werkkopie' ? $this->workingCopyData($cardObject, $worktreeEntity, $changes) : ['worktree' => $worktreeEntity]),
			...($tab === 'Context' ? $this->cardContextData($cardObject, $this->projectPathFor($cardObject), $worktreeEntity?->path ?? $this->projectPathFor($cardObject)) : []),
			'branchSuggestion' => $this->newBranch,
			'readableProjects' => $taskEntity?->valuesFor(TaskLinkKindEnum::READABLE_PROJECT) ?? [],
			'projectChoices' => GetProjectsAction::execute()->pluck('name')->sort()->values(),
			'branchChoices' => $cardObject->project !== null && ($tab === 'Koppelingen' || $isSetup)
				? GetBranchesAction::execute($cardObject->project)
				: [],
			'ticket' => $ticketEntity,
			'description' => $cardObject->description,
			'ticketRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::TICKET, array_filter([$cardObject->ticketKey]), $cardObject->ticketKey),
			'branchRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::BRANCH, array_filter([$cardObject->branch]), $cardObject->branch),
			'activePullRequest' => $cardObject->pullRequests->first(
				fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->repository . '#' . $pullRequestEntity->number === $cardObject->activePullRequest,
			),
			'pullRequestRows' => $this->linkRows($taskEntity, TaskLinkKindEnum::PULL_REQUEST, $pullRequests, $cardObject->activePullRequest),
			'reviewComments' => $reviewCommentEntities->reject(
				fn (ReviewCommentEntity $reviewCommentEntity) => $reviewCommentEntity->isPrompt,
			)->values(),
			'changesRequestedBy' => $cardObject->pullRequests->first(
				fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
			)?->changesRequestedBy,
			'fetchedAt' => GetLatestFetchRunAction::execute()?->finishedAt?->format('H:i'),
			'report' => $promptRunEntity?->output === null
				? null
				: resolve(RenderMarkdown::class)->toHtml($promptRunEntity->output),
		];
	}

	/**
	 * A finding of one lens in one round, opened on top of its card.
	 *
	 * @return array<string, mixed>|null
	 */
	private function findingContents(string $id, ?string $previousLabel): ?array
	{
		[$roundId, $lensId] = array_map('intval', explode(':', $id) + [1 => 0]);

		$reviewRoundEntity = resolve(ReviewRoundRepositoryContract::class)->getById(new ReviewRoundId(id: $roundId));
		$resultEntity = collect($reviewRoundEntity?->results ?? [])
			->first(fn (ReviewResultEntity $resultEntity) => $resultEntity->lensId->id === $lensId);

		return $resultEntity === null ? null : [
			'type' => 'finding',
			'round' => $reviewRoundEntity,
			'result' => $resultEntity,
			'report' => $resultEntity->report === null ? null : resolve(RenderMarkdown::class)->toHtml($resultEntity->report),
			'previous' => $previousLabel,
		];
	}

	/**
	 * The four steps on top of a card, with the one it stands on.
	 *
	 * @return array<int, array{label: string, state: string, number: int}>
	 */
	private function cardSteps(CardObject $cardObject): array
	{
		$order = [BoardColumnEnum::BUILDING, BoardColumnEnum::REVIEW, BoardColumnEnum::PULL_REQUEST, BoardColumnEnum::DONE];
		$current = array_search($cardObject->column, $order, true);

		return array_map(fn (BoardColumnEnum $column, int $index) => [
			'label' => $column->label(),
			'number' => $index + 1,
			'state' => match (true) {
				$current === false => 'todo',
				$index < $current || $column === BoardColumnEnum::DONE && $current === $index => 'done',
				$index === $current => 'now',
				default => 'todo',
			},
		], $order, array_keys($order));
	}

	/**
	 * What comes next on this task, in a line and with the tab where it is
	 * done.
	 *
	 * @return array{title: string, text: string, tone: string, hint: string, tab: ?string, button: ?string, url: ?string}
	 */
	private function nextStep(CardObject $cardObject, ?WorktreeEntity $worktreeEntity, ?ReviewRoundEntity $latestRound): array
	{
		$ahead = $worktreeEntity?->ahead ?? 0;
		$tone = match ($cardObject->status) {
			CardStatusEnum::YOURS => 'teal',
			CardStatusEnum::BUSY => 'amber',
			CardStatusEnum::QUIET, CardStatusEnum::WAITING => 'gray',
			CardStatusEnum::CONFLICT => 'red',
			CardStatusEnum::APPROVED => 'green',
		};

		[$text, $hint, $tab, $button] = match ($cardObject->column) {
			BoardColumnEnum::BUILDING => $worktreeEntity?->holdsWork()
				? [__('Lees na wat er veranderd is en leg het vast in een commit.'), __('Volgende stap: committen, op Werkkopie'), 'Werkkopie', __('Naar de werkkopie')]
				: [__('De agent bouwt in zijn eigen werkkopie; jij stuurt bij.'), __('Volgende stap: een opdracht, op Agent'), 'Agent', __('Naar de agent')],
			BoardColumnEnum::REVIEW => $latestRound?->status === RoundStatusEnum::PASSED
				? [__('Review is schoon. Na het pushen wacht de pull request op jou; mergen doe je in Bitbucket.'), __('Volgende stap: pushen, op Werkkopie'), 'Werkkopie', __('Naar de werkkopie')]
				: [__('De lenzen kijken naar wat deze branch veranderde.'), __('Volgende stap: de lenzen, op Review'), 'Review', __('Naar de review')],
			BoardColumnEnum::PULL_REQUEST => $cardObject->pullRequests->first(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested()) !== null
				? [__('Geef de opmerkingen aan de agent, of pak ze zelf op.'), __('Volgende stap: bijwerken, op Agent'), 'Agent', __('Naar de agent')]
				: [__('Collega\'s kijken; mergen doe je in Bitbucket.'), __('Volgende stap: de merge, in Bitbucket'), null, null],
			BoardColumnEnum::DONE => [__('Gemerged. De werkkopie mag weg.'), __('Volgende stap: opruimen, op Werkkopie'), 'Werkkopie', __('Naar de werkkopie')],
			default => [__('Nog niet ingericht.'), '', null, null],
		};

		return [
			'title' => $ahead > 0 && $cardObject->column === BoardColumnEnum::REVIEW
				? trans_choice(':count commit nog niet gepusht|:count commits nog niet gepusht', $ahead, ['count' => $ahead])
				: $cardObject->statusText,
			'text' => $text,
			'tone' => $tone,
			'hint' => $hint,
			'tab' => $tab,
			'button' => $button,
			'url' => $tab === null ? $cardObject->pullRequests->first()?->url : null,
		];
	}

	/**
	 * The name a database of this task would get: the one of the project with
	 * the ticket behind it.
	 */
	private function databaseSuggestionFor(CardObject $cardObject): ?string
	{
		$projectPath = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $cardObject->project)
			?->path;

		if ($projectPath === null) {
			return null;
		}

		return resolve(WorktreeDatabaseRepositoryContract::class)
			->describe($projectPath, $projectPath)
			->suggestionFor($cardObject->ticketKey ?? Str::slug(Str::limit($cardObject->title, 24, ''), '_'));
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

		if ($entry['type'] === 'finding') {
			return __('Bevinding');
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
		$cardObject = $this->cardById($this->board(), $card);

		return $cardObject?->suggestedCommitMessage() ?? '';
	}

	/**
	 * The folder the work of this task stands in: its own working copy when
	 * there is one, otherwise wherever the last round ran.
	 */
	private function workingDirectory(): ?string
	{
		$cardObject = $this->cardById($this->board(), (string) $this->openCard);

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
	 * The changed files of one view as a tree of folders. A new folder is
	 * opened up into its files, so each one has its own line and its own count.
	 *
	 * @return array<int, FileTreeNodeObject>
	 */
	private function diffTree(CardObject $cardObject, DiffViewEnum $diffView): array
	{
		$worktreeEntity = $this->worktreeFor($cardObject);

		return $worktreeEntity === null
			? []
			: BuildFileTreeAction::execute(collect(resolve(WorktreeRepositoryContract::class)->getChangesFor($worktreeEntity->path, $diffView))->values()->all());
	}

	/**
	 * Every changed file of one view, in the order of the tree, for the page
	 * that shows them under each other. Each file reads its own changes once
	 * it comes into view.
	 *
	 * @param  array<int, FileTreeNodeObject>  $diffTree
	 * @return array<int, array{change: ChangeObject, fingerprint: string, isLarge: bool, isGenerated: bool}>
	 */
	private function stackedDiffs(WorktreeEntity $worktreeEntity, DiffViewEnum $diffView, array $diffTree): array
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$changes = collect($worktreeRepository->getChangesFor($worktreeEntity->path, $diffView))->keyBy('path');
		$generatedFiles = $worktreeRepository->getGeneratedFiles($worktreeEntity->path, $changes->keys()->all());
		$files = [];

		foreach (array_merge([], ...array_map(fn (FileTreeNodeObject $node) => $node->filePaths(), $diffTree)) as $path) {
			$changeObject = $changes->get($path);

			if ($changeObject === null) {
				continue;
			}

			$wholePath = $worktreeEntity->path . '/' . $path;

			$files[] = [
				'change' => $changeObject,
				// read without git, so the page asks for no patch until a file is in view; a new save of the file un-views it
				'fingerprint' => md5(implode(':', [$changeObject->added, $changeObject->removed, @filemtime($wholePath) ?: 0, @filesize($wholePath) ?: 0])),
				// a file this large opens closed, so the page stays quick; opening it shows it in full
				'isLarge' => $changeObject->added + $changeObject->removed > self::LARGE_DIFF_LINES,
				'isGenerated' => in_array($path, $generatedFiles, true),
			];
		}

		return $files;
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
		return resolve(ReviewRoundRepositoryContract::class)->getLatestForCard($card);
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
	 * @return array<int, array{case: PromptSourceEnum, label: string}>
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
			$sources[] = ['case' => PromptSourceEnum::TICKET, 'label' => PromptSourceEnum::TICKET->label()];
		}

		// bij een ticket is de omschrijving het ticket zelf, alleen een to-do heeft een eigen notitie
		if ($cardObject->ticketKey === null && filled($cardObject->description)) {
			$sources[] = ['case' => PromptSourceEnum::NOTE, 'label' => PromptSourceEnum::NOTE->label()];
		}

		if ($findings->isNotEmpty()) {
			$reviewer = $cardObject->pullRequests->first(
				fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
			)?->changesRequestedBy;

			$sources[] = ['case' => PromptSourceEnum::REVIEW, 'label' => $reviewer === null
				? trans_choice('Review · :count open opmerking|Review · :count open opmerkingen', $findings->count(), ['count' => $findings->count()])
				: trans_choice(':name · :count open opmerking|:name · :count open opmerkingen', $findings->count(), ['name' => $reviewer, 'count' => $findings->count()])];
		}

		$latestRound = $this->latestRound($cardObject->id);
		$open = $latestRound?->openFindings() ?? 0;

		if ($open > 0) {
			$sources[] = ['case' => PromptSourceEnum::ROUND, 'label' => trans_choice('Bevindingen ronde :number · :count open|Bevindingen ronde :number · :count open', $open, ['number' => $latestRound->number, 'count' => $open])];
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
	/**
	 * The board of this request. Building it reads every source, so it is
	 * built once and shared by the actions and the render that follows.
	 *
	 * @return Collection<int, CardObject>
	 */
	/**
	 * Switches the tab and reads what is unread on both the tab you leave and
	 * the one you open.
	 */
	public function setDrawerTab(string $tab): void
	{
		if ($this->openCard !== null) {
			$this->markTabSeen($this->openCard, $this->openTab);
			$this->markTabSeen($this->openCard, $tab);
		}

		$this->switchDrawerTab($tab);
	}

	/**
	 * Takes the unread badge of one tab off the card; a tab no notification comes from changes nothing.
	 */
	private function markTabSeen(string $card, string $tab): void
	{
		$source = UnreadSourceEnum::tryFrom($tab);

		// a tab without a badge has nothing to take off, and writing anyway would make the board build again
		if ($source === null || ($this->cardById($this->board(), $card)?->unreadOnTab($tab) ?? 0) === 0) {
			return;
		}

		resolve(CardViewRepositoryContract::class)->markSeen($card, $source);
		$this->dispatch('board-changed')->to(Board::class);

		// the panel draws its own tab badges from the board too
		$this->cardObjects = null;
	}

	private function board(): Collection
	{
		return $this->cardObjects ??= collect(GetBoardAction::execute());
	}

	private function cardById(Collection $cardObjects, string $id): ?CardObject
	{
		return $cardObjects->first(fn (CardObject $cardObject) => $cardObject->id === $id);
	}

	public function render(): View
	{
		// a closed panel needs no board
		$drawer = $this->currentDrawer() === null ? null : $this->drawerContents($this->board());

		// the board marks the open card itself, without being drawn again
		$this->dispatch('drawer-card', card: ($drawer['type'] ?? null) === 'card' ? $drawer['card']->id : null);

		return view('livewire.drawer', [
			'drawer' => $drawer,
		]);
	}
}
