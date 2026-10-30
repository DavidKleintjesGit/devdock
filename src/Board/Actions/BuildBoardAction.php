<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Illuminate\Support\Collection;
use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\CardObjectCollection;
use Src\Board\Objects\TodoEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Task\Actions\GetTasksAction;
use Src\Task\Objects\TaskEntity;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;
use Src\Worktree\Actions\GetWorktreesAction;

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
	 * An open pull request nothing has happened to for this long asks for
	 * attention.
	 */
	private const STALE_AFTER_DAYS = 5;

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

	/** @var array<string, bool> */
	private array $waitingWorkByBranch = [];

	/** @var array<string, bool> */
	private array $deliveredByBranch = [];

	/** @var array<string, ReviewRoundEntity> */
	private array $roundsByCard = [];

	/** @var array<string, array<string, string>> */
	private array $pinned = [];

	/** @var Collection<string, TaskEntity> */
	private Collection $tasksByKey;

	public function handle(TodoRepositoryContract $todoRepository): CardObjectCollection
	{
		$projectEntities = collect(GetProjectsAction::execute());

		$this->ticketsByKey = collect(GetTicketsAction::execute())->keyBy('key');
		$this->projectNamesByRepository = $this->projectNamesByRepository($projectEntities);
		$this->waitingWorkByBranch = $this->waitingWorkByBranch();
		$this->deliveredByBranch = $this->deliveredByBranch();
		$this->roundsByCard = resolve(ReviewRoundRepositoryContract::class)->getLatestPerCard();
		$this->tasksByKey = collect(GetTasksAction::execute())->keyBy('key');
		$this->pinned = $this->pinned();
		$this->sessionEntities = collect(GetSessionsAction::execute())
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->branch !== null)
			->values();

		$cardObjects = $this->pieces($projectEntities, collect($todoRepository->getAll()))
			->groupBy('key')
			->map(fn (Collection $pieces, string $key) => $this->cardFrom($key, $pieces))
			->sortByDesc(fn (CardObject $cardObject) => $cardObject->changedAt)
			->values()
			->all();

		return CardObjectCollection::fromArray($cardObjects);
	}

	/**
	 * Everything that can point at a task, each piece carrying the key it falls
	 * under: the ticket number, otherwise the pull request, otherwise the branch
	 * in that project.
	 *
	 * @param  Collection<int, ProjectEntity>  $projectEntities
	 * @param  Collection<int, TodoEntity>  $todoEntities
	 * @return Collection<int, array<string, mixed>>
	 */
	private function pieces(Collection $projectEntities, Collection $todoEntities): Collection
	{
		$pullRequests = collect(GetPullRequestsAction::execute())
			->map(fn (PullRequestEntity $pullRequestEntity) => [
				'key' => $this->pinned['pull_request'][$pullRequestEntity->repository . '#' . $pullRequestEntity->number]
					?? $this->pinned['ticket'][(string) $pullRequestEntity->ticketKey()]
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
					?? $projectEntity->ticketKey()
					?? $this->ticketFromSessions($projectEntity->branch)
					?? 'branch:' . $projectEntity->name . '/' . $projectEntity->branch,
				'project' => $projectEntity,
			])
			->values();

		$started = $pullRequests->pluck('key')->concat($projects->pluck('key'));

		$tickets = $this->ticketsByKey
			->reject(fn (TicketEntity $ticketEntity) => $started->contains($ticketEntity->key))
			->map(fn (TicketEntity $ticketEntity) => [
				'key' => $this->pinned['ticket'][$ticketEntity->key] ?? $ticketEntity->key,
				'ticket' => $ticketEntity,
			])
			->values();

		$todos = $todoEntities->map(fn (TodoEntity $todoEntity) => [
			'key' => $todoEntity->boardKey(),
			'todo' => $todoEntity,
		]);

		return $pullRequests->concat($projects)->concat($tickets)->concat($todos);
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
			?? ($this->isTicketKey($key) ? $key : null);
		$ticketEntity = $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey);
		$branch = $taskEntity?->branch()
			?? $pullRequestEntities->first()?->sourceBranch
			?? $projectEntity?->branch;
		$sessionEntities = $this->sessionsFor($key, $branch);
		$latestRound = $this->roundsByCard[$key] ?? null;
		$column = $this->columnFor(
			$pullRequestEntities,
			$latestRound,
			$branch !== null || $sessionEntities->isNotEmpty(),
			$this->isDelivered($branch, $pullRequestEntities),
		);
		[$note, $tone] = $this->noteFor($column, $pullRequestEntities, $projectEntity, $sessionEntities, $latestRound);

		return new CardObject(
			id: $key,
			column: $column,
			title: $this->titleFor($pullRequestEntities, $ticketEntity, $sessionEntities, $branch),
			project: $taskEntity?->project()
				?? $this->projectNameFor($pullRequestEntities->first()?->repository)
				?? $projectEntity?->name,
			pullRequests: PullRequestEntityCollection::fromArray($pullRequestEntities->all()),
			sessions: SessionEntityCollection::fromArray($sessionEntities->all()),
			ticketKey: $ticketKey,
			ticketStatus: $ticketEntity?->status,
			ticketPriority: $ticketEntity?->priority,
			ticketUrl: $ticketEntity?->url,
			branch: $branch,
			changedFiles: $projectEntity?->changedFiles ?: null,
			changedAt: $pullRequestEntities->first()?->changedAt
				?? $ticketEntity?->changedAt
				?? $projectEntity?->scannedAt,
			description: $ticketEntity?->description,
			note: $note,
			tone: $tone,
			hasNameMismatch: $sessionEntities->contains(
				fn (SessionEntity $sessionEntity) => $sessionEntity->mismatchedTicket() !== null,
			),
			myRole: $this->roleFor($pullRequestEntities, $ticketEntity),
			isStale: $this->isStale($column, $pullRequestEntities),
			hasWaitingWork: $this->waitingWorkByBranch[$branch] ?? false,
			activePullRequest: $this->activePullRequest($taskEntity, $pullRequestEntities),
			agent: $taskEntity?->activeAgent(),
		);
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

	private function cardFromTodo(TodoEntity $todoEntity): CardObject
	{
		// a to-do is a task like any other: it may carry a project, a branch and
		// an agent of its own as soon as you tie them to it
		$taskEntity = $this->tasksByKey->get($todoEntity->boardKey());
		$ticketKey = $taskEntity?->ticketKey();

		return new CardObject(
			id: $todoEntity->boardKey(),
			column: $todoEntity->isDone() ? BoardColumnEnum::DONE : BoardColumnEnum::TO_DO,
			title: $todoEntity->title,
			project: $taskEntity?->project() ?? $todoEntity->project,
			pullRequests: PullRequestEntityCollection::fromArray([]),
			sessions: SessionEntityCollection::fromArray([]),
			ticketKey: $ticketKey,
			ticketStatus: $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey)?->status,
			ticketPriority: $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey)?->priority,
			ticketUrl: $ticketKey === null ? null : $this->ticketsByKey->get($ticketKey)?->url,
			branch: $taskEntity?->branch(),
			changedFiles: null,
			changedAt: $todoEntity->changedAt,
			description: $todoEntity->note,
			note: null,
			tone: null,
			hasNameMismatch: false,
			myRole: PullRequestRoleEnum::AUTHOR->value,
			isStale: false,
			hasWaitingWork: false,
			activePullRequest: $taskEntity?->pullRequest(),
			agent: $taskEntity?->activeAgent(),
		);
	}

	/**
	 * The column follows the pull request that asks loudest for attention: first
	 * what you have to pick up, then what sits with a colleague, then what is
	 * done.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	/**
	 * Waar een taak op het bord staat, afgeleid van de weg die hij aflegt en
	 * niet van de pull request alleen. De eerste commit is het keerpunt: pas
	 * dan is er iets opgeleverd om over te oordelen.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function columnFor(
		Collection $pullRequestEntities,
		?ReviewRoundEntity $latestRound,
		bool $isStarted,
		bool $isDelivered,
	): BoardColumnEnum {
		$open = $pullRequestEntities->filter(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen(),
		);

		if ($open->isEmpty() && $pullRequestEntities->isNotEmpty()) {
			return BoardColumnEnum::DONE;
		}

		if (! $isStarted) {
			return BoardColumnEnum::TO_DO;
		}

		if (! $isDelivered) {
			return BoardColumnEnum::IN_PROGRESS;
		}

		$changesRequested = $open->contains(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
		);

		// schoon teruggekomen, of een mens die het al goedkeurde: dan wacht het
		// alleen nog op het mergen zelf
		$approved = $latestRound?->status === RoundStatusEnum::PASSED
			|| $open->contains(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isApproved());

		return $approved && ! $changesRequested
			? BoardColumnEnum::READY_TO_MERGE
			: BoardColumnEnum::REVIEW;
	}

	/**
	 * Of er werk opgeleverd is: een commit op de branch, of een pull request,
	 * die er zonder commit niet kan zijn.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function isDelivered(?string $branch, Collection $pullRequestEntities): bool
	{
		return $pullRequestEntities->isNotEmpty()
			|| ($branch !== null && ($this->deliveredByBranch[$branch] ?? false));
	}

	/**
	 * What stands out about this task, with the colour it carries on the card.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @param  Collection<int, SessionEntity>  $sessionEntities
	 * @return array{0: ?string, 1: ?string}
	 */
	/**
	 * Waarom een taak in Nakijken staat. Dat kan van alles zijn, en alleen de
	 * draaiende ronde wacht niet op jou.
	 *
	 * @return array{0: ?string, 1: ?string}
	 */
	private function reviewNote(?ReviewRoundEntity $latestRound): array
	{
		$maxRounds = (int) config('devdock.review.max_rounds');

		if ($latestRound === null || $latestRound->status === RoundStatusEnum::STOPPED) {
			return [__('Mag naar de lenzen.'), 'amber'];
		}

		if ($latestRound->isRunning()) {
			return [__('Lenzen draaien · ronde :number van :max', [
				'number' => $latestRound->number,
				'max' => $maxRounds,
			]), 'teal'];
		}

		$open = $latestRound->openFindings();

		if ($open === 0) {
			return [__('Schoon. Akkoord om te committen?'), 'green'];
		}

		if ($latestRound->number >= $maxRounds) {
			return [trans_choice(
				':max rondes op, :count punt open.|:max rondes op, :count punten open.',
				$open,
				['max' => $maxRounds, 'count' => $open],
			), 'red'];
		}

		return [trans_choice(
			'De agent werkt :count punt bij.|De agent werkt :count punten bij.',
			$open,
			['count' => $open],
		), 'teal'];
	}

	private function noteFor(
		BoardColumnEnum $column,
		Collection $pullRequestEntities,
		?ProjectEntity $projectEntity,
		Collection $sessionEntities,
		?ReviewRoundEntity $latestRound = null,
	): array {
		$changesRequested = $pullRequestEntities->first(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
		);

		if ($changesRequested !== null) {
			return [__(':name vroeg om wijzigingen.', ['name' => $changesRequested->changesRequestedBy]), 'red'];
		}

		if ($column === BoardColumnEnum::REVIEW) {
			return $this->reviewNote($latestRound);
		}

		$activeToday = $sessionEntities->filter(
			fn (SessionEntity $sessionEntity) => $sessionEntity->lastActiveAt?->isToday(),
		)->count();

		if ($activeToday > 1) {
			return [__('Twee sessies werkten vandaag aan deze taak.'), 'amber'];
		}

		$open = $pullRequestEntities->filter(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen());

		if ($open->count() > 1) {
			return [__(':count open pull requests op dezelfde taak.', ['count' => $open->count()]), 'amber'];
		}

		if ($column === BoardColumnEnum::READY_TO_MERGE) {
			return [__('Goedgekeurd. Niets houdt het tegen.'), 'green'];
		}

		$mismatched = $sessionEntities->contains(
			fn (SessionEntity $sessionEntity) => $sessionEntity->mismatchedTicket() !== null,
		);

		if ($mismatched) {
			return [__('Sessienaam volgt het ticket niet.'), 'amber'];
		}

		$days = $open->first()?->daysSinceChange();

		if ($column === BoardColumnEnum::IN_PROGRESS && $days !== null && $days >= self::STALE_AFTER_DAYS) {
			return [__(':count dagen stil. Niemand heeft gekeken.', ['count' => $days]), 'amber'];
		}

		if (($projectEntity?->ahead ?? 0) > 0) {
			return [__(':count commits nog niet gepusht.', ['count' => $projectEntity?->ahead]), 'amber'];
		}

		return [null, null];
	}

	/**
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function isStale(BoardColumnEnum $column, Collection $pullRequestEntities): bool
	{
		if ($column !== BoardColumnEnum::IN_PROGRESS) {
			return false;
		}

		$days = $pullRequestEntities
			->first(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen())
			?->daysSinceChange();

		return $days !== null && $days >= self::STALE_AFTER_DAYS;
	}

	/**
	 * Your role on a task: author when you wrote the pull request or the ticket
	 * stands in your name, reviewer otherwise.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function roleFor(Collection $pullRequestEntities, ?TicketEntity $ticketEntity): ?string
	{
		$isAuthor = $pullRequestEntities->contains(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->myRole === PullRequestRoleEnum::AUTHOR,
		);

		if ($isAuthor || $ticketEntity !== null) {
			return PullRequestRoleEnum::AUTHOR->value;
		}

		$isReviewer = $pullRequestEntities->contains(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->myRole === PullRequestRoleEnum::REVIEWER,
		);

		return $isReviewer ? PullRequestRoleEnum::REVIEWER->value : null;
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
	 * Which branches have a working copy with work standing in it, so a card
	 * can say so without anyone opening it.
	 *
	 * @return array<string, bool>
	 */
	/**
	 * Per branch of er al een commit op staat.
	 *
	 * @return array<string, bool>
	 */
	private function deliveredByBranch(): array
	{
		$delivered = [];

		foreach (GetWorktreesAction::execute() as $worktreeEntity) {
			$delivered[$worktreeEntity->branch] = $worktreeEntity->hasCommits;
		}

		return $delivered;
	}

	private function waitingWorkByBranch(): array
	{
		$waiting = [];

		foreach (GetWorktreesAction::execute() as $worktreeEntity) {
			$waiting[$worktreeEntity->branch] = $worktreeEntity->holdsWork();
		}

		return $waiting;
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
			if (preg_match('/CSM-\d{3,5}/', (string) $sessionEntity->title, $found)) {
				return $found[0];
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

		return $this->sessionEntities
			->filter(fn (SessionEntity $sessionEntity) => str_contains((string) $sessionEntity->branch, $key)
				|| str_contains((string) $sessionEntity->title, $key))
			->values();
	}
}
