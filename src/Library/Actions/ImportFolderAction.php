<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Packages\Cqrs\Command;

/**
 * Brings what changed in a folder into the library as new versions. The
 * first time, the history git keeps of each file comes along, so the old
 * versions can be compared with as well.
 *
 * @method static int execute(int $libraryId, ?string $folder, ?string $skillsFolder, bool $withHistory = false)
 */
class ImportFolderAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
		public readonly ?string $folder,
		public readonly ?string $skillsFolder,
		public readonly bool $withHistory = false,
	) {}

	/**
	 * Returns how many versions came in.
	 */
	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): int {
		$libraryEntity = $libraryRepository->getById($this->libraryId);

		if ($libraryEntity === null) {
			return 0;
		}

		$added = 0;
		$sources = array_filter([
			LibraryItemKindEnum::RULE->value => $this->folder,
			LibraryItemKindEnum::SKILL->value => $this->skillsFolder,
		]);

		foreach ($sources as $kind => $folder) {
			$itemKind = LibraryItemKindEnum::from($kind);

			foreach ($libraryDiskRepository->readFolder($folder, $itemKind) as $name => $contents) {
				$before = $libraryEntity->item($itemKind, $name)?->latest()?->number ?? 0;

				if ($this->withHistory && $itemKind === LibraryItemKindEnum::RULE) {
					foreach ($libraryDiskRepository->historyOf($folder, $name) as $earlier) {
						$libraryRepository->addVersion($this->libraryId, $itemKind, $name, $earlier['contents'], VersionSourceEnum::FOLDER, $earlier['note'], null, $earlier['at']);
					}
				}

				$versionEntity = $libraryRepository->addVersion($this->libraryId, $itemKind, $name, $contents, VersionSourceEnum::FOLDER);

				$added += max(0, $versionEntity->number - $before);
			}
		}

		return $added;
	}
}
