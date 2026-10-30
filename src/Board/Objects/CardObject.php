<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Carbon\CarbonImmutable;
use Src\Board\Enums\BoardColumnEnum;
use Src\Packages\ValueObject\ValueObject;
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
		public readonly ?string $note,
		public readonly ?string $tone,
		public readonly bool $hasNameMismatch,
		public readonly ?string $myRole,
		public readonly bool $isStale,
		public readonly bool $hasWaitingWork,
		public readonly ?string $activePullRequest,
		public readonly ?TaskAgentEntity $agent,
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
	 * A commit message to start from: the ticket key, a dash and what the task
	 * is about, the way this repository writes them.
	 */
	public function suggestedCommitMessage(): string
	{
		$subject = trim($this->title);

		if ($this->ticketKey === null) {
			return $subject;
		}

		// the title of a pull request usually opens with the key already
		$subject = (string) preg_replace('/^' . preg_quote($this->ticketKey, '/') . '\s*[-:·]?\s*/i', '', $subject);

		return $this->ticketKey . ' - ' . $subject;
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
