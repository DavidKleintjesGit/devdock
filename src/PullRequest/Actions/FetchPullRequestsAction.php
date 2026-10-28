<?php

declare(strict_types=1);

namespace Src\PullRequest\Actions;

use Carbon\CarbonImmutable;
use Src\Packages\Cqrs\Command;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\PullRequest\Objects\PullRequestEntityCollection;

/**
 * Fetches the pull requests you author or review, plus what was merged
 * inside the Done window, and brings storage in line with them. The ones that
 * ask for changes also give up their open remarks.
 *
 * @method static PullRequestEntityCollection execute()
 */
class FetchPullRequestsAction extends Command
{
	public function handle(
		PullRequestSourceRepositoryContract $pullRequestSourceRepository,
		PullRequestRepositoryContract $pullRequestRepository,
	): PullRequestEntityCollection {
		if (! $pullRequestSourceRepository->isConfigured()) {
			return $pullRequestRepository->getAll();
		}

		// without a name everything from the whole workspace stays
		$reviewerName = (string) config('devdock.bitbucket.me') ?: null;
		$mergedSince = CarbonImmutable::now()->subDays((int) config('devdock.board.done_days'));

		$pullRequestObjects = $pullRequestSourceRepository->getOpenAndMerged($reviewerName, $mergedSince);
		$pullRequestEntities = $pullRequestRepository->replaceAll($pullRequestObjects);

		$this->fetchComments($pullRequestEntities, $pullRequestSourceRepository, $pullRequestRepository);

		return $pullRequestEntities;
	}

	/**
	 * Only a pull request that asks for changes is worth a call of its own;
	 * the remarks on the rest are not what the board is waiting for.
	 */
	private function fetchComments(
		PullRequestEntityCollection $pullRequestEntities,
		PullRequestSourceRepositoryContract $pullRequestSourceRepository,
		PullRequestRepositoryContract $pullRequestRepository,
	): void {
		$asking = $pullRequestEntities->filter(
			fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->hasChangesRequested(),
		);

		foreach ($asking as $pullRequestEntity) {
			$reviewCommentObjects = $pullRequestSourceRepository->getOpenComments(
				$pullRequestEntity->repository,
				$pullRequestEntity->number,
			);

			$pullRequestRepository->replaceComments($pullRequestEntity->pullRequestId, $reviewCommentObjects);
		}
	}
}
