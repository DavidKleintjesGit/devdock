<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Objects\LibraryVersionEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;

/**
 * Writes a new version of a library item: edited in DevDock, brought back
 * from a project, or an earlier version taken up again. History is never
 * thrown away.
 *
 * @method static ?LibraryVersionEntity execute(int $libraryId, LibraryItemKindEnum $kind, string $name, VersionSourceEnum $source, ?string $contents = null, ?string $note = null, ?string $project = null)
 */
class SaveVersionAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
		public readonly LibraryItemKindEnum $kind,
		public readonly string $name,
		public readonly VersionSourceEnum $source,
		public readonly ?string $contents = null,
		public readonly ?string $note = null,
		public readonly ?string $project = null,
	) {}

	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): ?LibraryVersionEntity {
		$contents = $this->contents ?? $this->fromProject($libraryRepository, $libraryDiskRepository);

		if ($contents === null) {
			return null;
		}

		return $libraryRepository->addVersion(
			$this->libraryId,
			$this->kind,
			$this->name,
			$contents,
			$this->source,
			$this->note,
			$this->project,
		);
	}

	/**
	 * What stands in the project for this item, for "terugzetten naar de
	 * bibliotheek".
	 */
	private function fromProject(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): ?string {
		$itemEntity = $libraryRepository->getById($this->libraryId)?->item($this->kind, $this->name);
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project);

		if ($itemEntity === null || $projectEntity === null) {
			return null;
		}

		[$landing, $folder] = GetLandingAction::execute($projectEntity->name, $projectEntity->path);

		return $libraryDiskRepository->readProjectItem($projectEntity->path, $itemEntity, $landing, $folder);
	}
}
