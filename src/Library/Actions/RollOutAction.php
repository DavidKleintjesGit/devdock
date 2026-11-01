<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryItemEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Rule\Actions\ScanRulesAction;

/**
 * Puts the newest version of an item down in the projects you picked. What
 * stood there is kept first, so nothing is lost. Only ever on your press:
 * nothing commits, and no project is written to on its own.
 *
 * @method static int execute(LibraryItemEntity $itemEntity, array $projects)
 */
class RollOutAction extends Command
{
	/**
	 * @param  array<int, string>  $projects
	 */
	public function __construct(
		public readonly LibraryItemEntity $itemEntity,
		public readonly array $projects,
	) {}

	/**
	 * Returns in how many projects it was written.
	 */
	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): int {
		$latest = $this->itemEntity->latest();

		if ($latest === null) {
			return 0;
		}

		$projectEntities = collect(GetProjectsAction::execute())->keyBy('name');
		$written = 0;

		foreach ($this->projects as $project) {
			$projectEntity = $projectEntities->get($project);

			if (! $projectEntity instanceof ProjectEntity) {
				continue;
			}

			[$landing, $folder] = GetLandingAction::execute($project, $projectEntity->path);

			$backup = $libraryDiskRepository->writeProjectItem($projectEntity->path, $this->itemEntity, $landing, $folder, $latest->contents);
			$libraryRepository->recordRollout($latest->id, $project, $landing->pathFor($this->itemEntity->name, $folder), $backup);

			$written++;
		}

		if ($written > 0) {
			ScanRulesAction::execute();
		}

		return $written;
	}
}
