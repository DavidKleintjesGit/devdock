<?php

declare(strict_types=1);

namespace Src\Fetch\Actions;

use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Packages\Cqrs\Command;

/**
 * @method static ?FetchRunEntity execute()
 */
class GetLatestFetchRunAction extends Command
{
	public function handle(FetchRunRepositoryContract $fetchRunRepository): ?FetchRunEntity
	{
		return $fetchRunRepository->getLatest();
	}
}
