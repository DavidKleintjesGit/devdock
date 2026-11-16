<?php

declare(strict_types=1);

namespace Src\PullRequest\Contracts;

use Carbon\CarbonImmutable;
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
	 * When the remarks people left on a pull request came in, per pull request, from this moment on.
	 *
	 * @return array<int, list<CarbonImmutable>>
	 */
	public function getCommentedSince(CarbonImmutable $since): array;

	/**
	 * Brings the stored remarks of one pull request in line with what is
	 * still open on it.
	 */
	public function replaceComments(
		PullRequestId $pullRequestId,
		ReviewCommentObjectCollection $reviewCommentObjects,
	): ReviewCommentEntityCollection;
}
