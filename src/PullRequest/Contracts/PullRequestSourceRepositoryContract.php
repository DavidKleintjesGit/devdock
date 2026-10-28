<?php

declare(strict_types=1);

namespace Src\PullRequest\Contracts;

use Carbon\CarbonImmutable;
use Src\PullRequest\Objects\PullRequestObjectCollection;
use Src\PullRequest\Objects\ReviewCommentObjectCollection;

interface PullRequestSourceRepositoryContract
{
	/**
	 * Whether a workspace, an account and a token are there to fetch with.
	 */
	public function isConfigured(): bool;

	/**
	 * Every open pull request in the workspace, plus what was merged since the
	 * given moment. With a name given, only what that person wrote or may judge
	 * is left.
	 */
	public function getOpenAndMerged(?string $reviewerName, CarbonImmutable $mergedSince): PullRequestObjectCollection;

	/**
	 * The remarks on one pull request that nobody has resolved yet.
	 */
	public function getOpenComments(string $repository, int $number): ReviewCommentObjectCollection;
}
