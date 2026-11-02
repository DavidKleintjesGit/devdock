<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Carbon\CarbonImmutable;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Packages\Cqrs\Command;

/**
 * Starts a round when the board has gone stale, so the sources keep up with
 * the day without anyone pressing anything.
 *
 * @method static ?FetchRunEntity execute()
 */
class RefreshWhenStaleAction extends Command
{
	public function handle(FetchRunRepositoryContract $fetchRunRepository): ?FetchRunEntity
	{
		$seconds = (int) config('devdock.refresh_seconds');

		if ($seconds <= 0) {
			return null;
		}

		$latest = $fetchRunRepository->getLatest();

		// a round that runs past the hang limit is dead, and must not hold up every round after it
		if ($latest?->isRunning() === true && $latest->startedWithin(StartFetchAction::HANGS_AFTER_MINUTES)) {
			return null;
		}

		$due = $latest?->finishedAt === null
			|| $latest->finishedAt->isBefore(CarbonImmutable::now()->subSeconds($seconds));

		return $due ? StartFetchAction::execute(true) : null;
	}
}
