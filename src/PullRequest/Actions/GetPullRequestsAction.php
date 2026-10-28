<?php

declare(strict_types=1);

namespace Src\PullRequest\Actions;

use Src\Packages\Cqrs\Command;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Objects\PullRequestEntityCollection;

/**
 * @method static PullRequestEntityCollection execute()
 */
class GetPullRequestsAction extends Command
{
	public function handle(PullRequestRepositoryContract $pullRequestRepository): PullRequestEntityCollection
	{
		return $pullRequestRepository->getAll();
	}
}
