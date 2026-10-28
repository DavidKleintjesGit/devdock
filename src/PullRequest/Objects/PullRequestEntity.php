<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Enums\PullRequestStateEnum;

/**
 * A pull request you wrote or were asked to judge.
 */
class PullRequestEntity extends ValueObject
{
	public function __construct(
		public readonly PullRequestId $pullRequestId,
		public readonly string $repository,
		public readonly int $number,
		public readonly string $title,
		public readonly PullRequestStateEnum $state,
		public readonly bool $isDraft,
		public readonly ?string $author,
		public readonly ?PullRequestRoleEnum $myRole,
		public readonly ?string $sourceBranch,
		public readonly ?string $destinationBranch,
		public readonly int $approvals,
		public readonly int $reviewers,
		public readonly ?string $changesRequestedBy,
		public readonly int $commentCount,
		public readonly ?string $url,
		public readonly ?CarbonImmutable $changedAt,
	) {
		parent::__construct();
	}

	/**
	 * The ticket this pull request belongs to, from the title or the branch.
	 */
	public function ticketKey(): ?string
	{
		preg_match('/CSM-\d{3,5}/', $this->title . ' ' . $this->sourceBranch, $found);

		return $found[0] ?? null;
	}

	public function isOpen(): bool
	{
		return $this->state === PullRequestStateEnum::OPEN;
	}

	public function isMerged(): bool
	{
		return $this->state === PullRequestStateEnum::MERGED;
	}

	public function hasChangesRequested(): bool
	{
		return filled($this->changesRequestedBy);
	}

	/**
	 * Whether anyone has approved it already.
	 */
	public function isApproved(): bool
	{
		return $this->approvals > 0;
	}

	/**
	 * How many days nothing has happened to this pull request.
	 */
	public function daysSinceChange(): ?int
	{
		return $this->changedAt === null ? null : (int) $this->changedAt->diffInDays();
	}

	/**
	 * The key this pull request falls under on the board when no ticket number
	 * hangs on it.
	 */
	public function boardKey(): string
	{
		return $this->ticketKey() ?? 'pr:' . $this->repository . '#' . $this->number;
	}
}
