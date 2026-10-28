<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionThresholdObject;

/**
 * @method static SessionEntityCollection execute()
 */
class GetSessionsAction extends Command
{
	public function handle(SessionRepositoryContract $sessionRepository): SessionEntityCollection
	{
		return $sessionRepository->getAll(SessionThresholdObject::from(config('devdock.sessions')));
	}
}
