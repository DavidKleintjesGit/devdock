<?php

declare(strict_types=1);

namespace Src\Fetch\Repositories;

use Src\Fetch\Contracts\FetchProcessRepositoryContract;
use Src\Fetch\Objects\FetchRunId;
use Src\Packages\Process\DetachedCommand;

class FetchProcessRepository implements FetchProcessRepositoryContract
{
	public function startDetached(FetchRunId $fetchRunId, bool $dueOnly): void
	{
		resolve(DetachedCommand::class)->artisan(
			sprintf('devdock:fetch --run=%d', (int) $fetchRunId->id) . ($dueOnly ? ' --due' : ''),
		);
	}

	public function binaryInUse(): string
	{
		return resolve(DetachedCommand::class)->phpBinary();
	}
}
