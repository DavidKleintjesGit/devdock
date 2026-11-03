<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Carbon\CarbonImmutable;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Enums\CardStatusEnum;
use Src\Board\Enums\CopyPlaceEnum;
use Src\Packages\ValueObject\ValueObject;
use Src\Prompt\Objects\CommitMessageObject;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionEntityCollection;
use Src\Task\Objects\TaskAgentEntity;

/**
 * One task on the board: what belongs together under the same ticket number
 * — the ticket, the pull requests and the sessions that worked on it.
 */
class CardObject extends ValueObject
{
	public function __construct(
		public readonly string $id,
		public readonly BoardColumnEnum $column,
		public readonly string $title,
		public readonly ?string $project,
		public readonly PullRequestEntityCollection $pullRequests,
		public readonly SessionEntityCollection $sessions,
		public readonly ?string $ticketKey,
		public readonly ?string $ticketStatus,
		public readonly ?string $ticketPriority,
		public readonly ?string $ticketUrl,
		public readonly ?string $branch,
		public readonly ?int $changedFiles,
		public readonly ?CarbonImmutable $changedAt,
		public readonly ?string $description,
		public readonly CardStatusEnum $status,
		public readonly string $statusText,
		public readonly array $lensStates,
		public readonly bool $hasNameMismatch,
		public readonly bool $isStale,
		public readonly int $behind,
		public readonly ?string $mainBranch,
		public readonly bool $hasWaitingWork,
		public readonly ?string $activePullRequest,
		public readonly ?TaskAgentEntity $agent,
		public readonly array $conflictedFiles = [],
		public readonly ?CopyPlaceEnum $copyPlace = null,
	) {
		parent::__construct();
	}

	/**
	 * Whether Jira marks this ticket as one of the two highest priorities.
	 */
	public function isHighPriority(): bool
	{
		return in_array($this->ticketPriority, ['Highest', 'High'], true);
	}

	/**
	 * A commit message to start from: the ticket key and what the task is
	 * about.
	 */
	public function suggestedCommitMessage(): string
	{
		return CommitMessageObject::from(['title' => $this->title])
			->withTicketKey($this->ticketKey)
			->title;
	}

	public function matches(string $term): bool
	{
		$numbers = $this->pullRequests
			->map(fn (PullRequestEntity $pullRequestEntity) => '#' . $pullRequestEntity->number)
			->implode(' ');

		$haystack = strtolower(implode(' ', array_filter([
			$this->ticketKey,
			$this->title,
			$this->branch,
			$this->project,
			$numbers,
		])));

		return str_contains($haystack, strtolower($term));
	}

	public function dateLabel(): ?string
	{
		return $this->changedAt?->translatedFormat('j M');
	}

	/**
	 * How long ago the task last moved, as short as the card allows.
	 */
	public function ageLabel(): ?string
	{
		if ($this->changedAt === null) {
			return null;
		}

		$minutes = (int) $this->changedAt->diffInMinutes(CarbonImmutable::now(), absolute: true);

		return match (true) {
			$minutes < 60 => __(':count min', ['count' => max(1, $minutes)]),
			$minutes < 60 * 24 => __(':count u', ['count' => intdiv($minutes, 60)]),
			$this->changedAt->isYesterday() => __('gisteren'),
			$minutes < 60 * 24 * 14 => __(':count d', ['count' => intdiv($minutes, 60 * 24)]),
			default => __(':count w', ['count' => intdiv($minutes, 60 * 24 * 7)]),
		};
	}

	/**
	 * Whether the next move on this task is yours.
	 */
	public function isYourTurn(): bool
	{
		return $this->status->isYours() && $this->column !== BoardColumnEnum::DONE;
	}

	public function hasConflict(): bool
	{
		return $this->status === CardStatusEnum::CONFLICT;
	}

	public function isInBacklog(): bool
	{
		return $this->column === BoardColumnEnum::LATER;
	}

	public function isDone(): bool
	{
		return $this->column === BoardColumnEnum::DONE;
	}

	/**
	 * Whether this is a to-do of your own rather than a ticket or a branch.
	 */
	public function isTodo(): bool
	{
		return str_starts_with($this->id, 'todo:');
	}

	public function promptCount(): int
	{
		return (int) $this->sessions->sum('promptCount');
	}

	public function commentCount(): int
	{
		return (int) $this->pullRequests->sum('commentCount');
	}

	/**
	 * Two sessions that worked on the same task today are two Claudes in the
	 * same files; that is the only session case that belongs on the board.
	 */
	public function hasClashingSessions(): bool
	{
		return $this->sessions
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->lastActiveAt?->isToday())
			->count() > 1;
	}
}
