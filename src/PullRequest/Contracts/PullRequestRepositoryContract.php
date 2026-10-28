<?php

declare(strict_types=1);

namespace Src\PullRequest\Contracts;

use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;
use Src\PullRequest\Objects\PullRequestObject;
use Src\PullRequest\Objects\PullRequestId;
use Src\PullRequest\Objects\PullRequestObjectCollection;
use Src\PullRequest\Objects\ReviewCommentEntityCollection;
use Src\PullRequest\Objects\ReviewCommentObjectCollection;

interface PullRequestRepositoryContract
{
	public function create(PullRequestObject $pullRequestObject): PullRequestEntity;

	public function getAll(): PullRequestEntityCollection;

	/**
	 * Brings the stored pull requests in line with what Bitbucket returned. An
	 * empty list leaves everything standing: Bitbucket was unreachable then, not
	 * empty.
	 */
	public function replaceAll(PullRequestObjectCollection $pullRequestObjects): PullRequestEntityCollection;

	public function getComments(PullRequestId $pullRequestId): ReviewCommentEntityCollection;

	/**
	 * Brings the stored remarks of one pull request in line with what is
	 * still open on it.
	 */
	public function replaceComments(
		PullRequestId $pullRequestId,
		ReviewCommentObjectCollection $reviewCommentObjects,
	): ReviewCommentEntityCollection;
}
