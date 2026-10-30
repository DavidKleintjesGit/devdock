<?php

declare(strict_types=1);

namespace Src\Review\Repositories;

use Src\Packages\Process\DetachedCommand;
use Src\Review\Contracts\LensProcessRepositoryContract;

class LensProcessRepository implements LensProcessRepositoryContract
{
	public function startWorkers(int $count): void
	{
		$detachedCommand = resolve(DetachedCommand::class);

		// every worker keeps going until the queue is empty. One that falls over
		// then costs speed and not a lens: the others pick its work up
		$command = sprintf(
			'queue:work --queue=lenses --stop-when-empty --timeout=%d',
			(int) config('devdock.review.lens_timeout') + 120,
		);

		for ($worker = 0; $worker < $count; $worker++) {
			$detachedCommand->artisan($command);
		}
	}
}
