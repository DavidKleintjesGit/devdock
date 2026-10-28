<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Src\Packages\Cqrs\Command;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;

/**
 * How many of the four sources can be reached: Jira, Bitbucket, the sites
 * folder and the sessions folder.
 *
 * @method static int execute()
 */
class CountConnectedSourcesAction extends Command
{
	public function handle(
		TicketSourceRepositoryContract $ticketSourceRepository,
		PullRequestSourceRepositoryContract $pullRequestSourceRepository,
	): int {
		$connected = [
			$ticketSourceRepository->isConfigured(),
			$pullRequestSourceRepository->isConfigured(),
			is_dir((string) config('devdock.paths.sites')),
			is_dir((string) config('devdock.paths.sessions')),
		];

		return count(array_filter($connected));
	}
}
