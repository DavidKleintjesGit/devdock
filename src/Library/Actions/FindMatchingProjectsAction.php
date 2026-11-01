<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryItemEntity;
use Src\Library\Objects\LibraryVersionEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;

/**
 * Per project how many of its files are a version of an item in this
 * library, so the projects that already hold it can follow it.
 *
 * @method static array<string, int> execute(int $libraryId)
 */
class FindMatchingProjectsAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
	) {}

	/**
	 * @return array<string, int>
	 */
	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): array {
		$libraryEntity = $libraryRepository->getById($this->libraryId);

		if ($libraryEntity === null) {
			return [];
		}

		$matches = [];

		foreach (GetProjectsAction::execute() as $projectEntity) {
			$count = 0;

			foreach ($libraryEntity->items as $itemEntity) {
				$files = $libraryDiskRepository->projectFiles($projectEntity->path, $itemEntity->kind);
				$contents = $files[$itemEntity->name] ?? null;

				if ($contents !== null && $this->isAVersionOf($itemEntity, $contents)) {
					$count++;
				}
			}

			$matches[$projectEntity->name] = $count;
		}

		arsort($matches);

		return $matches;
	}

	private function isAVersionOf(LibraryItemEntity $itemEntity, string $contents): bool
	{
		return $itemEntity->versionWithHash(LibraryVersionEntity::hashOf($contents)) !== null;
	}
}
