<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Enums\StandingEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryItemEntity;
use Src\Library\Objects\LibraryVersionEntity;
use Src\Library\Objects\StandingObject;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;

/**
 * How every item of a library stands in the given projects. A project that
 * does not follow the library is left out unless it is asked for.
 *
 * @method static array<int, StandingObject> execute(LibraryEntity $libraryEntity, ?array $projects = null, ?LibraryItemEntity $onlyItem = null, ?string $path = null)
 */
class GetStandingsAction extends Command
{
	/**
	 * @param  array<int, string>|null  $projects  null means every follower
	 * @param  string|null  $path  a working copy to look in instead of the project folder
	 */
	public function __construct(
		public readonly LibraryEntity $libraryEntity,
		public readonly ?array $projects = null,
		public readonly ?LibraryItemEntity $onlyItem = null,
		public readonly ?string $path = null,
	) {}

	/**
	 * @return array<int, StandingObject>
	 */
	public function handle(LibraryDiskRepositoryContract $libraryDiskRepository): array
	{
		$projectEntities = collect(GetProjectsAction::execute())->keyBy('name');
		$standings = [];

		foreach ($this->projects ?? $this->libraryEntity->followers as $project) {
			$projectEntity = $projectEntities->get($project);

			if (! $projectEntity instanceof ProjectEntity) {
				continue;
			}

			$root = $this->path ?? $projectEntity->path;
			[$landing, $folder] = GetLandingAction::execute($project, $projectEntity->path);

			$items = $this->onlyItem === null ? $this->libraryEntity->items : [$this->onlyItem];

			foreach ($items as $itemEntity) {
				$contents = $libraryDiskRepository->readProjectItem($root, $itemEntity, $landing, $folder);
				$onVersion = $contents === null ? null : $itemEntity->versionWithHash(LibraryVersionEntity::hashOf($contents));

				$standing = match (true) {
					$contents === null => StandingEnum::MISSING,
					$onVersion === null => StandingEnum::MODIFIED,
					$onVersion->number === $itemEntity->latest()?->number => StandingEnum::EQUAL,
					default => StandingEnum::BEHIND,
				};

				$standings[] = new StandingObject(
					project: $project,
					item: $itemEntity,
					standing: $standing,
					onVersion: $onVersion,
					path: $landing->pathFor($itemEntity->name, $folder),
					lines: $contents === null ? null : LibraryVersionEntity::lineCountOf($contents),
				);
			}
		}

		return $standings;
	}
}
