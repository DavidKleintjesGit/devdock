<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionThresholdObject;

/**
 * Reads the Claude Code sessions folder and brings the stored sessions in
 * line with the files standing there.
 *
 * @method static SessionEntityCollection execute()
 */
class ScanSessionsAction extends Command
{
	public function handle(
		SessionFileRepositoryContract $sessionFileRepository,
		SessionRepositoryContract $sessionRepository,
	): SessionEntityCollection {
		$thresholds = SessionThresholdObject::from(config('devdock.sessions'));

		$sessionObjects = $sessionFileRepository->getAll(
			GetProjectsAction::execute(),
			$sessionRepository->getAll($thresholds),
		);

		return $sessionRepository->replaceAll($sessionObjects, $thresholds);
	}
}
