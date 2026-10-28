<?php

declare(strict_types=1);

namespace Src\PullRequest\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\PullRequest\Enums\PullRequestRoleEnum;
use Src\PullRequest\Enums\PullRequestStateEnum;

/**
 * A pull request as Bitbucket returned it.
 */
class PullRequestObject extends ValueObject
{
	public function __construct(
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

	public static function from(array $data): self
	{
		return new self(
			repository: (string) $data['repository'],
			number: (int) $data['number'],
			title: (string) $data['title'],
			state: PullRequestStateEnum::from((string) $data['state']),
			isDraft: (bool) $data['draft'],
			author: $data['author'] ?? null,
			myRole: isset($data['my_role']) ? PullRequestRoleEnum::from((string) $data['my_role']) : null,
			sourceBranch: $data['source_branch'] ?? null,
			destinationBranch: $data['destination_branch'] ?? null,
			approvals: (int) $data['approvals'],
			reviewers: (int) $data['reviewers'],
			changesRequestedBy: $data['changes_requested_by'] ?? null,
			commentCount: (int) ($data['comment_count'] ?? 0),
			url: $data['url'] ?? null,
			changedAt: isset($data['changed_at']) ? CarbonImmutable::parse($data['changed_at']) : null,
		);
	}
}
