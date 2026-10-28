<?php

declare(strict_types=1);

namespace Src\Project\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Contracts\ProjectRepositoryContract;
use Src\Project\Objects\ProjectEntityCollection;

/**
 * @method static ProjectEntityCollection execute()
 */
class GetProjectsAction extends Command
{
	public function handle(ProjectRepositoryContract $projectRepository): ProjectEntityCollection
	{
		return $projectRepository->getAll();
	}
}
