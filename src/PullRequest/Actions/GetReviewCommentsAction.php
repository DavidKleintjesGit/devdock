<?php

declare(strict_types=1);

namespace Src\PullRequest\Actions;

use Src\Packages\Cqrs\Command;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Objects\PullRequestId;
use Src\PullRequest\Objects\ReviewCommentEntityCollection;

/**
 * @method static ReviewCommentEntityCollection execute(PullRequestId $pullRequestId)
 */
class GetReviewCommentsAction extends Command
{
	public function __construct(
		public readonly PullRequestId $pullRequestId,
	) {
	}

	public function handle(PullRequestRepositoryContract $pullRequestRepository): ReviewCommentEntityCollection
	{
		return $pullRequestRepository->getComments($this->pullRequestId);
	}
}
