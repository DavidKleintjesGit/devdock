<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Src\Board\Contracts\CardViewRepositoryContract;
use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\CardStatusEnum;
use Src\Board\Enums\CopyPlaceEnum;
use Src\Board\Enums\UnreadSourceEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\CardObjectCollection;
use Src\Board\Objects\TodoEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Prompt\Actions\ReadReportAction;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Enums\ReportStatusEnum;
use Src\Prompt\Objects\PromptRunEntity;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;
use Src\Review\Actions\GetWorkflowSettingAction;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Task\Actions\GetTasksAction;
use Src\Task\Objects\TaskEntity;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;
use Src\Ticket\Objects\TicketKey;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\BranchStandingObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Every task on the board. A task is what belongs together: a Jira ticket,
 * the pull requests with that ticket number and the sessions that worked on
 * it. Without a ticket number the pull request itself is the task, and
 * without a pull request the local branch is.
 *
 * @method static CardObjectCollection execute()
 */
class BuildBoardAction extends Command
{
	/**
	 * A branch that is not a task but the main line of a project.
	 */
	private const MAIN_BRANCHES = ['master', 'main', 'HEAD'];

	/** @var Collection<string, TicketEntity> */
	private Collection $ticketsByKey;

	/** @var Collection<int, SessionEntity> */
	private Collection $sessionEntities;

	/** @var array<string, string> */
	private array $projectNamesByRepository = [];

	/** @var Collection<int, WorktreeEntity> */
	private Collection $worktreeEntities;

	/** @var array<string, ReviewRoundEntity> */
	private array $roundsByCard = [];

	/** @var array<string, PromptRunEntity> */
	private array $promptRunsByCard = [];

	/** @var array<string, array<string, string>> */
	private array $pinned = [];

	/** @var Collection<string, TaskEntity> */
	private Collection $tasksByKey;

	/** @var array<string, int> */
	private array $maxRoundsByProject = [];

	/**
	 * Whether Jira tells sprint from backlog at all. Without sprints every
	 * ticket is work for now, or the board would hide all of it.
	 */
	private bool $knowsSprints = false;

	/** @var Collection<string, ProjectEntity> */
	private Collection $projectsByName;

	/** @var array<string, array<string, CarbonImmutable>> */
	private array $seenAt = [];

	private CarbonImmutable $readUntil;

	/** @var array<string, array<int, CarbonImmutable>> */
	private array $finishedTurns = [];

	/** @var array<string, array<int, CarbonImmutable>> */
	private array $finishedRounds = [];

	public function handle(TodoRepositoryContract $todoRepository): CardObjectCollection
	{
		$projectEntities = collect(GetProjectsAction::execute());

		$this->ticketsByKey = collect(GetTicketsAction::execute())->keyBy('key');
		$this->knowsSprints = $this->ticketsByKey->contains(fn (TicketEntity $ticketEntity) => $ticketEntity->isInSprint());
		$this->projectNamesByRepository = $this->projectNamesByRepository($projectEntities);
		$this->worktreeEntities = collect(GetWorktreesAction::execute());
		$this->roundsByCard = resolve(ReviewRoundRepositoryContract::class)->getLatestPerCard();
		$this->promptRunsByCard = resolve(PromptRunRepositoryContract::class)->getLatestPerCard();
		$this->tasksByKey = collect(GetTasksAction::execute())->keyBy('key');
		$this->pinned = $this->pinned();
		$this->sessionEntities = collect(GetSessionsAction::execute())
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->branch !== null)
			->values();

		$this->projectsByName = $projectEntities->keyBy('name');
		$this->seenAt = resolve(CardViewRepositoryContract::class)->getSeenAt();
		$this->readUntil = resolve(CardViewRepositoryContract::class)->getReadUntil();
		$this->finishedTurns = resolve(PromptRunRepositoryContract::class)->getFinishedSince($this->readUntil);
		$this->finishedRounds = resolve(ReviewRoundRepositoryContract::class)->getFinishedSince($this->readUntil);

		$cardObjects = $this->pieces($projectEntities, collect($todoRepository->getAll()))
			->groupBy('key')
			->map(fn (Collection $pieces, string $key) => $this->cardFrom($key, $pieces))
			->sort(fn (CardObject $first, CardObject $second) => $this->order($first, $second))
			->values()
			->all();

		return CardObjectCollection::fromArray($cardObjects);
	}

	/**
	 * What an agent worked on last first, then a conflict, then what moved last.
	 */
	private function order(CardObject $first, CardObject $second): int
	{
		return [$second->agentActiveAt?->getTimestamp() ?? 0, $second->hasConflict(), $second->changedAt?->getTimestamp() ?? 0]
			<=> [$first->agentActiveAt?->getTimestamp() ?? 0, $first->hasConflict(), $first->changedAt?->getTimestamp() ?? 0];
	}

	/**
	 * How many agent turns or review rounds finished since you last opened the
	 * tab they belong to, per source.
	 *
	 * @return array<string, int>
	 */
	private function unreadPerSource(string $card): array
	{
		$finished = [
			UnreadSourceEnum::AGENT->value => $this->finishedTurns[$card] ?? [],
			UnreadSourceEnum::REVIEW->value => $this->finishedRounds[$card] ?? [],
		];

		$unread = [];

		foreach ($finished as $source => $moments) {
			$seenAt = $this->seenAtFor($card, UnreadSourceEnum::from($source));

			$unread[$source] = collect($moments)
				->filter(fn (CarbonImmutable $moment) => $moment->greaterThan($seenAt))
				->count();
		}

		return $unread;
	}

	/**
	 * Whether the last turn of the agent ended in a question you have not seen yet.
	 */
	private function hasUnreadQuestion(string $card, ?PromptRunEntity $promptRunEntity): bool
	{
		return $promptRunEntity?->finishedAt?->greaterThan($this->seenAtFor($card, UnreadSourceEnum::AGENT)) === true
			&& ReadReportAction::execute($promptRunEntity->output)?->status === ReportStatusEnum::QUESTION;
	}

	private function seenAtFor(string $card, UnreadSourceEnum $source): CarbonImmutable
	{
		return $this->seenAt[$card][$source->value] ?? $this->readUntil;
	}

	/**
	 * When the agent of a task last worked on it: now while it runs, else when its last turn ended.
	 */
	private function agentActiveAt(?PromptRunEntity $promptRunEntity): ?CarbonImmutable
	{
		return $promptRunEntity?->isRunning()
			? CarbonImmutable::now()
			: ($promptRunEntity?->finishedAt ?? $promptRunEntity?->startedAt);
	}

	private function standingFor(?string $project, ?string $branch): ?BranchStandingObject
	{
		$projectEntity = $project === null ? null : $this->projectsByName->get($project);

		if ($projectEntity === null || $branch === null || in_array($branch, self::MAIN_BRANCHES, true)) {
			return null;
		}

		// read by the fetch round, so the board never waits on git
		return resolve(BranchStandingRepositoryContract::class)->getKnown($projectEntity->path, $branch);
	}

	/**
	 * Where the branch is checked out: a working copy of its own, the project
	 * folder itself, or nowhere yet.
	 */
	private function copyPlaceFor(?string $project, ?string $branch, ?WorktreeEntity $worktreeEntity): ?CopyPlaceEnum
	{
		if ($project === null || $branch === null) {
			return null;
		}

		return match (true) {
			$this->projectsByName->get($project)?->branch === $branch, $worktreeEntity?->origin === WorktreeOriginEnum::PROJECT => CopyPlaceEnum::MAIN_CHECKOUT,
			$worktreeEntity !== null => CopyPlaceEnum::OWN,
			default => CopyPlaceEnum::NONE,
		};
	}

	/**
	 * Everything that can point at a task, each piece carrying the key it falls
	 * under: the ticket number, otherwise the pull request, otherwise the branch
	 * in that project. A pull request you only review is someone else's task
	 * and stays off the board.
	 *
	 * @param  Collection<int, ProjectEntity>  $projectEntities
	 * @param  Collection<int, TodoEntity>  $todoEntities
	 * @return Collection<int, array<string, mixed>>
	 */
	private function pieces(Collection $projectEntities, Collection $todoEntities): Collection
	{
		$pullRequests = collect(GetPullRequestsAction::execute())
			->reject(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->myRole === PullRequestRoleEnum::REVIEWER)
			->map(fn (PullRequestEntity $pullRequestEntity) => [
				'key' => $this->pinned['pull_request'][$pullRequestEntity->repository . '#' . $pullRequestEntity->number]
					// a pull request opened from the branch a task already holds belongs to that task
					?? $this->pinned['branch'][(string) $pullRequestEntity->sourceBranch]
					?? $this->pinned['ticket'][$pullRequestEntity->boardKey()]
					?? $pullRequestEntity->boardKey(),
				'pullRequest' => $pullRequestEntity,
			]);

		$branches = $pullRequests->pluck('pullRequest.sourceBranch')->filter()->all();

		$projects = $projectEntities
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->branch !== null)
			->reject(fn (ProjectEntity $projectEntity) => in_array($projectEntity->branch, self::MAIN_BRANCHES, true))
			->reject(fn (ProjectEntity $projectEntity) => in_array($projectEntity->branch, $branches, true))
			->reject(fn (ProjectEntity $projectEntity) => $projectEntity->changedFiles === 0 && $projectEntity->ahead === 0)
			->map(fn (ProjectEntity $projectEntity) => [
				'key' => $this->pinned['branch'][$projectEntity->name . '/' . $projectEntity->branch]
					?? TicketKey::ofBranch($projectEntity->branch)
					?? $this->ticketFromSessions($projectEntity->branch)
					?? 'branch:' . $projectEntity->name . '/' . $projectEntity->branch,
				'project' => $projectEntity,
			])
			->values();

		$started = $pullRequests->pluck('key')->concat($projects->pluck('key'));

		// a ticket worked on only in a variant branch has its card there, not a second empty one
		$startedTickets = $started->map(fn (string $key): ?string => TicketKey::find($key))->filter();

		$tickets = $this->ticketsByKey
			->reject(fn (TicketEntity $ticketEntity) => $startedTickets->contains($ticketEntity->key))
			->map(fn (TicketEntity $ticketEntity) => [
				'key' => $this->pinned['ticket'][$ticketEntity->key] ?? $ticketEntity->key,
				'ticket' => $ticketEntity,
			])
			->values();

		$doneSince = CarbonImmutable::now()->subDays((int) config('devdock.board.done_days'));

		// Klaar looks back as far as it does for pull requests, not forever
		$todos = $todoEntities
			->reject(fn (TodoEntity $todoEntity) => $todoEntity->doneAt?->lessThan($doneSince) ?? false)
			->map(fn (TodoEntity $todoEntity) => [
				'key' => $todoEntity->boardKey(),
				'todo' => $todoEntity,
			]);

		// a working copy keeps its task on the board until it is cleaned up, also when Jira closed or reassigned the ticket
		$workingCopies = $this->worktreeEntities
			->filter(fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->card !== null)
			->map(fn (WorktreeEntity $worktreeEntity) => [
				'key' => (string) $worktreeEntity->card,
				'worktree' => $worktreeEntity,
			])
			->values();

		return $pullRequests->concat($projects)->concat($tickets)->concat($todos)->concat($workingCopies);
	}

	/**
	 * @param  Collection<int, array<string, mixed>>  $pieces
	 */
	private function cardFrom(string $key, Collection $pieces): CardObject
	{
		$todoEntity = $pieces->pluck('todo')->filter()->first();

		if ($todoEntity !== null) {
			return $this->cardFromTodo($todoEntity);
		}

		$pullRequestEntities = $pieces->pluck('pullRequest')->filter()->values();
		$projectEntity = $pieces->pluck('project')->filter()->first();
		$taskEntity = $this->tasksByKey->get($key);
		$ticketKey = $taskEntity?->ticketKey()
			?? ($this->isTicketKey($key) ? (TicketKey::find($key) ?? $key) : null);
		$ticketEntity = $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey);
		$workingCopy = $pieces->pluck('worktree')->filter()->first();
		$branch = $taskEntity?->branch()
			?? $pullRequestEntities->first()?->sourceBranch
			?? $projectEntity?->branch
			?? $workingCopy?->branch;
		$project = $taskEntity?->project()
			?? $this->projectNameFor($pullRequestEntities->first()?->repository)
			?? $projectEntity?->name
			?? $workingCopy?->project;
		$sessionEntities = $this->sessionsFor($key, $branch);
		$worktreeEntity = $this->worktreeFor($project, $branch);
		$latestRound = $this->roundsByCard[$key] ?? null;
		$promptRunEntity = $this->promptRunsByCard[$key] ?? null;
		$agent = $taskEntity?->activeAgent();

		$column = $this->columnFor(
			$pullRequestEntities,
			$ticketEntity,
			$branch !== null || $sessionEntities->isNotEmpty() || $agent !== null,
			$pullRequestEntities->isNotEmpty() || ($worktreeEntity?->hasCommits ?? false),
		);

		$lastActivity = $this->lastActivity($pullRequestEntities, $sessionEntities, $worktreeEntity, $latestRound, $promptRunEntity, $agent?->startedAt);
		$quietDays = $lastActivity === null ? null : (int) $lastActivity->diffInDays(CarbonImmutable::now(), absolute: true);
		$isStale = $column->canStall() && $quietDays !== null && $quietDays >= (int) config('devdock.board.stale_days');

		[$status, $statusText] = $this->statusFor(
			$project,
			$column,
			$pullRequestEntities,
			$ticketEntity,
			$latestRound,
			$promptRunEntity,
			$agent !== null,
			$worktreeEntity,
			$isStale ? $quietDays : null,
		);

		$standing = $column->canStall() ? $this->standingFor($project, $branch) : null;

		// a clash with the main line blocks the merge, so it outweighs every other turn but work under way
		if ($standing?->hasConflict() && $column !== BoardColumnEnum::BUILDING && $status !== CardStatusEnum::BUSY) {
			[$status, $statusText] = [CardStatusEnum::CONFLICT, trans_choice(
				'Conflict in :count bestand|Conflict in :count bestanden',
				count($standing->conflictedFiles),
				['count' => count($standing->conflictedFiles)],
			)];
		}

		return new CardObject(
			id: $key,
			column: $column,
			title: $this->titleFor($pullRequestEntities, $ticketEntity, $sessionEntities, $branch),
			project: $project,
			pullRequests: PullRequestEntityCollection::fromArray($pullRequestEntities->all()),
			sessions: SessionEntityCollection::fromArray($sessionEntities->all()),
			ticketKey: $ticketKey,
			ticketStatus: $ticketEntity?->status,
			ticketPriority: $ticketEntity?->priority,
			ticketUrl: $ticketEntity?->url,
			branch: $branch,
			changedFiles: $projectEntity?->changedFiles ?: null,
			changedAt: $lastActivity ?? $ticketEntity?->changedAt ?? $projectEntity?->scannedAt,
			description: $ticketEntity?->description,
			status: $status,
			statusText: $statusText,
			lensStates: $column === BoardColumnEnum::REVIEW ? $this->lensStates($latestRound) : [],
			hasNameMismatch: $sessionEntities->contains(
				fn (SessionEntity $sessionEntity) => $sessionEntity->mismatchedTicket() !== null,
			),
			isStale: $isStale,
			behind: $column->canStall() ? ($standing?->behind ?? $worktreeEntity?->behind ?? 0) : 0,
			mainBranch: $worktreeEntity?->mainBranch,
			targetBranch: $standing?->targetBranch ?? $worktreeEntity?->targetBranch,
			hasWaitingWork: $worktreeEntity?->holdsWork() ?? false,
			activePullRequest: $this->activePullRequest($taskEntity, $pullRequestEntities),
			agent: $agent,
			conflictedFiles: $standing?->conflictedFiles ?? [],
			copyPlace: $column->canStall() ? $this->copyPlaceFor($project, $branch, $worktreeEntity) : null,
			agentActiveAt: $this->agentActiveAt($promptRunEntity),
			unreadPerSource: $this->unreadPerSource($key),
			hasUnreadQuestion: $this->hasUnreadQuestion($key, $promptRunEntity),
		);
	}

	private function cardFromTodo(TodoEntity $todoEntity): CardObject
	{
		// a to-do is a task like any other: it may carry a project, a branch and
		// an agent of its own as soon as you tie them to it
		$taskEntity = $this->tasksByKey->get($todoEntity->boardKey());
		$ticketKey = $taskEntity?->ticketKey();
		$ticketEntity = $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey);
		$agent = $taskEntity?->activeAgent();
		$promptRunEntity = $this->promptRunsByCard[$todoEntity->boardKey()] ?? null;

		$column = match (true) {
			$todoEntity->isDone() => BoardColumnEnum::DONE,
			// work tied to it outweighs "for later", as with a ticket in the backlog
			$agent !== null || $taskEntity?->branch() !== null => BoardColumnEnum::BUILDING,
			$todoEntity->isLater => BoardColumnEnum::LATER,
			default => BoardColumnEnum::TO_DO,
		};

		[$status, $statusText] = match ($column) {
			BoardColumnEnum::DONE => [CardStatusEnum::QUIET, __('Afgevinkt')],
			BoardColumnEnum::LATER => [CardStatusEnum::QUIET, __('Eigen to-do, voor later')],
			BoardColumnEnum::BUILDING => $this->buildingStatus($promptRunEntity, $agent !== null, null),
			default => [CardStatusEnum::QUIET, __('Nog niet ingericht')],
		};

		return new CardObject(
			id: $todoEntity->boardKey(),
			column: $column,
			title: $todoEntity->title,
			project: $taskEntity?->project() ?? $todoEntity->project,
			pullRequests: PullRequestEntityCollection::fromArray([]),
			sessions: SessionEntityCollection::fromArray([]),
			ticketKey: $ticketKey,
			ticketStatus: $ticketEntity?->status,
			ticketPriority: $ticketEntity?->priority,
			ticketUrl: $ticketEntity?->url,
			branch: $taskEntity?->branch(),
			changedFiles: null,
			changedAt: $promptRunEntity?->finishedAt ?? $promptRunEntity?->startedAt ?? $todoEntity->changedAt,
			description: $todoEntity->note,
			status: $status,
			statusText: $statusText,
			lensStates: [],
			hasNameMismatch: false,
			isStale: false,
			behind: 0,
			mainBranch: null,
			targetBranch: null,
			hasWaitingWork: false,
			activePullRequest: $taskEntity?->pullRequest(),
			agent: $agent,
			agentActiveAt: $this->agentActiveAt($promptRunEntity),
			unreadPerSource: $this->unreadPerSource($todoEntity->boardKey()),
			hasUnreadQuestion: $this->hasUnreadQuestion($todoEntity->boardKey(), $promptRunEntity),
		);
	}

	/**
	 * Where a task stands in the way of working. The first commit is the
	 * turning point: only then is there something for the lenses to judge,
	 * and a pull request means it left the machine.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function columnFor(
		Collection $pullRequestEntities,
		?TicketEntity $ticketEntity,
		bool $isStarted,
		bool $isDelivered,
	): BoardColumnEnum {
		$hasOpen = $pullRequestEntities->contains(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen(),
		);

		return match (true) {
			$pullRequestEntities->isNotEmpty() && ! $hasOpen => BoardColumnEnum::DONE,
			$hasOpen => BoardColumnEnum::PULL_REQUEST,
			$isDelivered => BoardColumnEnum::REVIEW,
			$isStarted => BoardColumnEnum::BUILDING,
			$this->knowsSprints && $ticketEntity !== null && ! $ticketEntity->isInSprint() => BoardColumnEnum::LATER,
			default => BoardColumnEnum::TO_DO,
		};
	}

	/**
	 * Who the task waits on, and the one line that says what happens now.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @return array{0: CardStatusEnum, 1: string}
	 */
	private function statusFor(
		?string $project,
		BoardColumnEnum $column,
		Collection $pullRequestEntities,
		?TicketEntity $ticketEntity,
		?ReviewRoundEntity $latestRound,
		?PromptRunEntity $promptRunEntity,
		bool $hasAgent,
		?WorktreeEntity $worktreeEntity,
		?int $staleDays,
	): array {
		// work under way outweighs every other line, in whichever column the task stands
		$busy = $column->canStall() ? $this->busyStatus($column, $latestRound, $promptRunEntity, $project) : null;

		if ($busy !== null) {
			return $busy;
		}

		return match ($column) {
			BoardColumnEnum::LATER => [CardStatusEnum::QUIET, __('In de backlog, nog niet gepland')],
			BoardColumnEnum::TO_DO => $hasAgent
				? [CardStatusEnum::YOURS, __('Ingericht · jouw beurt: bouwen')]
				: [CardStatusEnum::QUIET, __('Nog niet ingericht')],
			BoardColumnEnum::BUILDING => $this->buildingStatus($promptRunEntity, $hasAgent, $staleDays, $worktreeEntity),
			BoardColumnEnum::REVIEW => $latestRound?->isExpiredBy($worktreeEntity === null ? null : resolve(WorktreeRepositoryContract::class)->getFingerprint($worktreeEntity->path))
				? [CardStatusEnum::YOURS, __('Gewijzigd na de ronde · opnieuw naar de lenzen')]
				: $this->reviewStatus($latestRound, $staleDays, $this->maxRoundsFor($project)),
			BoardColumnEnum::PULL_REQUEST => $this->pullRequestStatus($pullRequestEntities, $staleDays),
			BoardColumnEnum::DONE => [CardStatusEnum::QUIET, $ticketEntity !== null || $pullRequestEntities->isNotEmpty() ? __('Gemerged') : __('Afgevinkt')],
		};
	}

	/**
	 * The lenses of a round or the agent at work on the task; null when
	 * nothing is running.
	 *
	 * @return array{0: CardStatusEnum, 1: string}|null
	 */
	private function busyStatus(BoardColumnEnum $column, ?ReviewRoundEntity $latestRound, ?PromptRunEntity $promptRunEntity, ?string $project): ?array
	{
		if ($latestRound?->isRunning()) {
			return [CardStatusEnum::BUSY, __('Ronde :number van :max · lenzen bezig', [
				'number' => $latestRound->positionInSeries(),
				'max' => $this->maxRoundsFor($project),
			])];
		}

		if (! $promptRunEntity?->isRunning()) {
			return null;
		}

		return [CardStatusEnum::BUSY, $column === BoardColumnEnum::REVIEW
			? __('De agent werkt de bevindingen bij')
			: __('Agent bezig · :count min', ['count' => max(1, intdiv((int) $promptRunEntity->seconds(), 60))])];
	}

	/**
	 * @return array{0: CardStatusEnum, 1: string}
	 */
	private function buildingStatus(
		?PromptRunEntity $promptRunEntity,
		bool $hasAgent,
		?int $staleDays,
		?WorktreeEntity $worktreeEntity = null,
	): array {
		if ($promptRunEntity?->isRunning()) {
			return [CardStatusEnum::BUSY, __('Agent bezig · :count min', ['count' => max(1, intdiv((int) $promptRunEntity->seconds(), 60))])];
		}

		if ($staleDays !== null) {
			return [CardStatusEnum::QUIET, $this->quietFor($staleDays)];
		}

		if ($promptRunEntity?->hasFailed()) {
			return [CardStatusEnum::YOURS, __('Jouw beurt: de agent liep vast')];
		}

		if ($promptRunEntity !== null && $promptRunEntity->reviewRoundId === null) {
			return [CardStatusEnum::YOURS, __('Jouw beurt: de agent is klaar')];
		}

		if ($worktreeEntity?->holdsWork()) {
			return [CardStatusEnum::YOURS, __('Jouw beurt: werk staat klaar in de werkkopie')];
		}

		return $hasAgent
			? [CardStatusEnum::YOURS, __('Jouw beurt: geef de agent een opdracht')]
			: [CardStatusEnum::YOURS, __('Jouw beurt: inrichten')];
	}

	/**
	 * @return array{0: CardStatusEnum, 1: string}
	 */
	private function reviewStatus(?ReviewRoundEntity $latestRound, ?int $staleDays, int $maxRounds): array
	{
		if ($staleDays !== null) {
			return [CardStatusEnum::QUIET, $this->quietFor($staleDays)];
		}

		if ($latestRound === null || $latestRound->status === RoundStatusEnum::STOPPED) {
			return [CardStatusEnum::YOURS, __('Jouw beurt: naar de lenzen')];
		}

		$open = $latestRound->openFindings();

		// a lens that fell over never looked, so its round is not clean
		if ($open === 0 && $latestRound->fallenLenses() > 0) {
			return [CardStatusEnum::YOURS, trans_choice(
				':count lens viel om · jouw beurt|:count lenzen vielen om · jouw beurt',
				$latestRound->fallenLenses(),
				['count' => $latestRound->fallenLenses()],
			)];
		}

		if ($latestRound->status === RoundStatusEnum::PASSED || $open === 0) {
			return [CardStatusEnum::YOURS, __('Schoon · jouw beurt: committen en pushen')];
		}

		if ($latestRound->positionInSeries() >= $maxRounds) {
			return [CardStatusEnum::YOURS, trans_choice(
				':max rondes op, :count bevinding open|:max rondes op, :count bevindingen open',
				$open,
				['max' => $maxRounds, 'count' => $open],
			)];
		}

		return [CardStatusEnum::YOURS, trans_choice(
			':count bevinding open · jouw beurt|:count bevindingen open · jouw beurt',
			$open,
			['count' => $open],
		)];
	}

	/**
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @return array{0: CardStatusEnum, 1: string}
	 */
	private function pullRequestStatus(Collection $pullRequestEntities, ?int $staleDays): array
	{
		$open = $pullRequestEntities->first(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen());

		if ($open?->hasChangesRequested()) {
			return [CardStatusEnum::YOURS, __(':name vraagt wijzigingen', ['name' => $open->changesRequestedBy])];
		}

		if ($open === null) {
			return [CardStatusEnum::QUIET, __('Wacht op de merge')];
		}

		if ($open->reviewers > 0 && $open->approvals >= $open->reviewers) {
			return [CardStatusEnum::APPROVED, __(':approvals van :reviewers goedgekeurd · mergen in Bitbucket', [
				'approvals' => $open->approvals,
				'reviewers' => $open->reviewers,
			])];
		}

		if ($staleDays !== null) {
			return [CardStatusEnum::QUIET, $this->quietFor($staleDays)];
		}

		return [CardStatusEnum::WAITING, $open->reviewers === 0
			? __('Wacht op reviewers')
			: __(':approvals van :reviewers goedgekeurd · wacht op collega\'s', [
				'approvals' => $open->approvals,
				'reviewers' => $open->reviewers,
			])];
	}

	/**
	 * How many rounds a project allows, asked once per project.
	 */
	private function maxRoundsFor(?string $project): int
	{
		return $this->maxRoundsByProject[(string) $project] ??= GetWorkflowSettingAction::execute($project)->maxRounds;
	}

	private function quietFor(int $days): string
	{
		return trans_choice('Niets veranderd sinds :count dag|Niets veranderd sinds :count dagen', $days, ['count' => $days]);
	}

	/**
	 * One state per lens that was called: passed, failed, busy or waiting.
	 *
	 * @return array<int, string>
	 */
	private function lensStates(?ReviewRoundEntity $latestRound): array
	{
		if ($latestRound === null) {
			return [];
		}

		return array_map(fn (ReviewResultEntity $resultEntity) => match ($resultEntity->verdict) {
			VerdictEnum::PASS => 'pass',
			VerdictEnum::FAIL, VerdictEnum::ERROR => 'fail',
			VerdictEnum::RUNNING => 'busy',
			default => 'wait',
		}, $latestRound->called());
	}

	/**
	 * The last sign of life on a task: a commit, a message to or from the
	 * agent, a review round, a reaction on the pull request or a session.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @param  Collection<int, SessionEntity>  $sessionEntities
	 */
	private function lastActivity(
		Collection $pullRequestEntities,
		Collection $sessionEntities,
		?WorktreeEntity $worktreeEntity,
		?ReviewRoundEntity $latestRound,
		?PromptRunEntity $promptRunEntity,
		?CarbonImmutable $agentStartedAt,
	): ?CarbonImmutable {
		return collect([
			$worktreeEntity?->lastCommitAt,
			$latestRound?->finishedAt ?? $latestRound?->startedAt,
			$promptRunEntity?->finishedAt ?? $promptRunEntity?->startedAt,
			$agentStartedAt,
			...$pullRequestEntities->pluck('changedAt')->all(),
			...$sessionEntities->pluck('lastActiveAt')->all(),
		])
			->filter()
			->sortByDesc(fn (CarbonImmutable $moment) => $moment->getTimestamp())
			->first();
	}

	/**
	 * The pull request this task works with: the one it was told to use, and
	 * otherwise the first one that is still open.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function activePullRequest(?TaskEntity $taskEntity, Collection $pullRequestEntities): ?string
	{
		$chosen = $taskEntity?->pullRequest();

		if ($chosen !== null) {
			return $chosen;
		}

		$pullRequestEntity = $pullRequestEntities->first(
			fn (PullRequestEntity $entity) => $entity->isOpen(),
		) ?? $pullRequestEntities->first();

		return $pullRequestEntity === null
			? null
			: $pullRequestEntity->repository . '#' . $pullRequestEntity->number;
	}

	private function worktreeFor(?string $project, ?string $branch): ?WorktreeEntity
	{
		if ($project === null || $branch === null) {
			return null;
		}

		return $this->worktreeEntities->first(
			fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->project === $project && $worktreeEntity->branch === $branch,
		);
	}

	/**
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @param  Collection<int, SessionEntity>  $sessionEntities
	 */
	private function titleFor(
		Collection $pullRequestEntities,
		?TicketEntity $ticketEntity,
		Collection $sessionEntities,
		?string $branch,
	): string {
		return $pullRequestEntities->first()?->title
			?? $ticketEntity?->summary
			?? $sessionEntities->first()?->title
			?? $branch
			?? __('Zonder naam');
	}

	/**
	 * The folder name for a repository, so the board uses one name per project
	 * instead of two.
	 */
	private function projectNameFor(?string $repository): ?string
	{
		if ($repository === null) {
			return null;
		}

		return $this->projectNamesByRepository[$repository] ?? $repository;
	}

	/**
	 * What is pinned where, as kind and value to the key of the task that
	 * claims it. A pin beats anything the board would work out itself.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function pinned(): array
	{
		$pinned = ['ticket' => [], 'branch' => [], 'pull_request' => [], 'project' => []];

		foreach ($this->tasksByKey as $taskEntity) {
			foreach ($taskEntity->links as $taskLinkEntity) {
				$pinned[$taskLinkEntity->kind->value][$taskLinkEntity->value] = $taskEntity->key;
			}
		}

		return $pinned;
	}

	/**
	 * The folder name per repository name, so a pull request lands with the
	 * project on disk.
	 *
	 * @param  Collection<int, ProjectEntity>  $projectEntities
	 * @return array<string, string>
	 */
	private function projectNamesByRepository(Collection $projectEntities): array
	{
		$names = [];

		// two folders can point at the same repository, such as a second checkout;
		// the shortest name is the main folder then
		foreach ($projectEntities->sortBy('name') as $projectEntity) {
			$slug = $projectEntity->repositorySlug();

			if ($slug !== null && ! isset($names[$slug])) {
				$names[$slug] = $projectEntity->name;
			}
		}

		return $names;
	}

	/**
	 * A key is a ticket number, or a made-up key for a task that has none.
	 */
	private function isTicketKey(string $key): bool
	{
		return ! str_starts_with($key, 'pr:') && ! str_starts_with($key, 'branch:');
	}

	/**
	 * The ticket number from the name of a session that worked on this branch.
	 * That way a branch without a number still lands with its ticket.
	 */
	private function ticketFromSessions(?string $branch): ?string
	{
		if ($branch === null) {
			return null;
		}

		$onBranch = $this->sessionEntities->filter(
			fn (SessionEntity $sessionEntity) => $sessionEntity->branch === $branch,
		);

		foreach ($onBranch as $sessionEntity) {
			$ticketKey = TicketKey::find($sessionEntity->title);

			if ($ticketKey !== null) {
				return $ticketKey;
			}
		}

		return null;
	}

	/**
	 * The sessions that belong to a task: everything with this ticket number in
	 * the branch or in the name, and otherwise whatever worked on exactly this
	 * branch.
	 *
	 * @return Collection<int, SessionEntity>
	 */
	private function sessionsFor(string $key, ?string $branch): Collection
	{
		if (! $this->isTicketKey($key)) {
			if ($branch === null) {
				return collect();
			}

			return $this->sessionEntities
				->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->branch === $branch)
				->values();
		}

		// a session on a branch with a ticket belongs to the task of that branch, so a variant keeps its own sessions
		return $this->sessionEntities
			->filter(fn (SessionEntity $sessionEntity) => TicketKey::ofBranch($sessionEntity->branch) !== null
				? TicketKey::ofBranch($sessionEntity->branch) === $key
				: str_contains((string) $sessionEntity->title, $key))
			->values();
	}
}
