<?php

declare(strict_types=1);

namespace Src\Project\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Contracts\ProjectRepositoryContract;
use Src\Project\Contracts\ProjectScanRepositoryContract;
use Src\Project\Objects\ProjectEntityCollection;

/**
 * Reads the sites folder and brings the stored projects in line with what
 * stands there.
 *
 * @method static ProjectEntityCollection execute()
 */
class ScanProjectsAction extends Command
{
	public function handle(
		ProjectScanRepositoryContract $projectScanRepository,
		ProjectRepositoryContract $projectRepository,
	): ProjectEntityCollection {
		$projectObjects = $projectScanRepository->getAll((string) config('devdock.paths.sites'));

		return $projectRepository->replaceAll($projectObjects);
	}
}
