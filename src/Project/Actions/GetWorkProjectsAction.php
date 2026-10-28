<?php

declare(strict_types=1);

namespace Src\Project\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Contracts\ProjectRepositoryContract;
use Src\Project\Objects\ProjectEntity;
use Src\Project\Objects\ProjectEntityCollection;

/**
 * The projects that belong to your own Bitbucket workspace; that is what
 * counts as work.
 *
 * @method static ProjectEntityCollection execute()
 */
class GetWorkProjectsAction extends Command
{
	public function handle(ProjectRepositoryContract $projectRepository): ProjectEntityCollection
	{
		$workspace = (string) config('devdock.bitbucket.workspace');

		$projectEntities = $projectRepository->getAll()
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->belongsToWorkspace($workspace))
			->values()
			->all();

		return ProjectEntityCollection::fromArray($projectEntities);
	}
}
