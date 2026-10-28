<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Src\Fetch\Contracts\FetchProcessRepositoryContract;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Packages\Cqrs\Command;

/**
 * Sets a round along the sources ready and has it run outside the request.
 * When a round is already running, that is the round.
 *
 * @method static FetchRunEntity execute()
 */
class StartFetchAction extends Command
{
	/**
	 * A round still running after this has hung.
	 */
	private const HANGS_AFTER_MINUTES = 10;

	public function handle(
		FetchRunRepositoryContract $fetchRunRepository,
		FetchProcessRepositoryContract $fetchProcessRepository,
	): FetchRunEntity {
		$running = $fetchRunRepository->getRunning();

		if ($running !== null && $running->startedWithin(self::HANGS_AFTER_MINUTES)) {
			return $running;
		}

		$fetchRunEntity = $fetchRunRepository->create();

		$fetchProcessRepository->startDetached($fetchRunEntity->fetchRunId);

		return $fetchRunEntity;
	}
}
