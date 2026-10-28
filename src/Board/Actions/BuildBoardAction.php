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
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Ticket\Actions\GetTicketsAction;
use Src\Ticket\Objects\TicketEntity;

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

	public function handle(TodoRepositoryContract $todoRepository): CardObjectCollection
	{
		$projectEntities = collect(GetProjectsAction::execute());

		$this->ticketsByKey = collect(GetTicketsAction::execute())->keyBy('key');
		$this->projectNamesByRepository = $this->projectNamesByRepository($projectEntities);
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
				'key' => $pullRequestEntity->boardKey(),
				'pullRequest' => $pullRequestEntity,
			]);

		$branches = $pullRequests->pluck('pullRequest.sourceBranch')->filter()->all();

		$projects = $projectEntities
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->branch !== null)
			->reject(fn (ProjectEntity $projectEntity) => in_array($projectEntity->branch, self::MAIN_BRANCHES, true))
			->reject(fn (ProjectEntity $projectEntity) => in_array($projectEntity->branch, $branches, true))
			->reject(fn (ProjectEntity $projectEntity) => $projectEntity->changedFiles === 0 && $projectEntity->ahead === 0)
			->map(fn (ProjectEntity $projectEntity) => [
				'key' => $projectEntity->ticketKey()
					?? $this->ticketFromSessions($projectEntity->branch)
					?? 'branch:' . $projectEntity->name . '/' . $projectEntity->branch,
				'project' => $projectEntity,
			])
			->values();

		$started = $pullRequests->pluck('key')->concat($projects->pluck('key'));

		$tickets = $this->ticketsByKey
			->reject(fn (TicketEntity $ticketEntity) => $started->contains($ticketEntity->key))
			->map(fn (TicketEntity $ticketEntity) => ['key' => $ticketEntity->key, 'ticket' => $ticketEntity])
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
		$ticketEntity = $this->ticketsByKey->get($key);
		$branch = $pullRequestEntities->first()?->sourceBranch ?? $projectEntity?->branch;
		$sessionEntities = $this->sessionsFor($key, $branch);
		$column = $this->columnFor($pullRequestEntities, $projectEntity);
		[$note, $tone] = $this->noteFor($column, $pullRequestEntities, $projectEntity, $sessionEntities);

		return new CardObject(
			id: $key,
			column: $column,
			title: $this->titleFor($pullRequestEntities, $ticketEntity, $sessionEntities, $branch),
			project: $this->projectNameFor($pullRequestEntities->first()?->repository) ?? $projectEntity?->name,
			pullRequests: PullRequestEntityCollection::fromArray($pullRequestEntities->all()),
			sessions: SessionEntityCollection::fromArray($sessionEntities->all()),
			ticketKey: $this->isTicketKey($key) ? $key : null,
			ticketStatus: $ticketEntity?->status,
			ticketPriority: $ticketEntity?->priority,
			ticketUrl: $ticketEntity?->url,
			branch: $branch,
			changedFiles: $projectEntity?->changedFiles ?: null,
			changedAt: $pullRequestEntities->first()?->changedAt
				?? $ticketEntity?->changedAt
				?? $projectEntity?->scannedAt,
			note: $note,
			tone: $tone,
			hasNameMismatch: $sessionEntities->contains(
				fn (SessionEntity $sessionEntity) => $sessionEntity->mismatchedTicket() !== null,
			),
			myRole: $this->roleFor($pullRequestEntities, $ticketEntity),
			isStale: $this->isStale($column, $pullRequestEntities),
		);
	}

	private function cardFromTodo(TodoEntity $todoEntity): CardObject
	{
		return new CardObject(
			id: $todoEntity->boardKey(),
			column: $todoEntity->isDone() ? BoardColumnEnum::DONE : BoardColumnEnum::TO_DO,
			title: $todoEntity->title,
			project: $todoEntity->project,
			pullRequests: PullRequestEntityCollection::fromArray([]),
			sessions: SessionEntityCollection::fromArray([]),
			ticketKey: null,
			ticketStatus: null,
			ticketPriority: null,
			ticketUrl: null,
			branch: null,
			changedFiles: null,
			changedAt: $todoEntity->changedAt,
			note: $todoEntity->note,
			tone: $todoEntity->note === null ? null : 'blue',
			hasNameMismatch: false,
			myRole: PullRequestRoleEnum::AUTHOR->value,
			isStale: false,
		);
	}

	/**
	 * The column follows the pull request that asks loudest for attention: first
	 * what you have to pick up, then what sits with a colleague, then what is
	 * done.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 */
	private function columnFor(Collection $pullRequestEntities, ?ProjectEntity $projectEntity): BoardColumnEnum
	{
		$open = $pullRequestEntities->filter(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isOpen(),
		);

		if ($open->contains(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested())) {
			return BoardColumnEnum::CHANGES_REQUESTED;
		}

		$waiting = $open->contains(
			fn (PullRequestEntity $pullRequestEntity) => ! $pullRequestEntity->isApproved() || $pullRequestEntity->isDraft,
		);

		if ($waiting) {
			return BoardColumnEnum::IN_PROGRESS;
		}

		if ($open->isNotEmpty()) {
			return BoardColumnEnum::READY_TO_MERGE;
		}

		if ($pullRequestEntities->isNotEmpty()) {
			return BoardColumnEnum::DONE;
		}

		return $projectEntity === null ? BoardColumnEnum::TO_DO : BoardColumnEnum::IN_PROGRESS;
	}

	/**
	 * What stands out about this task, with the colour it carries on the card.
	 *
	 * @param  Collection<int, PullRequestEntity>  $pullRequestEntities
	 * @param  Collection<int, SessionEntity>  $sessionEntities
	 * @return array{0: ?string, 1: ?string}
	 */
	private function noteFor(
		BoardColumnEnum $column,
		Collection $pullRequestEntities,
		?ProjectEntity $projectEntity,
		Collection $sessionEntities,
	): array {
		$changesRequested = $pullRequestEntities->first(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
		);

		if ($changesRequested !== null) {
			return [__(':name vroeg om wijzigingen.', ['name' => $changesRequested->changesRequestedBy]), 'red'];
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
