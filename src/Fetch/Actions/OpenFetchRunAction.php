<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Packages\Cqrs\Command;

/**
 * Opens a round to keep the progress in, without starting a process with it.
 * That is what the command needs when it is already running itself.
 *
 * @method static FetchRunEntity execute()
 */
class OpenFetchRunAction extends Command
{
	public function handle(FetchRunRepositoryContract $fetchRunRepository): FetchRunEntity
	{
		return $fetchRunRepository->create();
	}
}
