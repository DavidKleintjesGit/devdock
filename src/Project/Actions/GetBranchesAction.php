<?php

declare(strict_types=1);

namespace Src\Project\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Contracts\ProjectScanRepositoryContract;
use Src\Project\Objects\ProjectEntity;

/**
 * The branches of one project, most recently worked on first.
 *
 * @method static array execute(string $project)
 */
class GetBranchesAction extends Command
{
	/**
	 * A checkout can hold hundreds of branches; only the ones recently worked
	 * on are worth choosing from.
	 */
	private const MOST_RECENT = 40;

	public function __construct(
		public readonly string $project,
	) {}

	/**
	 * @return list<string>
	 */
	public function handle(ProjectScanRepositoryContract $projectScanRepository): array
	{
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project);

		return $projectEntity === null
			? []
			: array_slice($projectScanRepository->getBranches($projectEntity->path), 0, self::MOST_RECENT);
	}
}
