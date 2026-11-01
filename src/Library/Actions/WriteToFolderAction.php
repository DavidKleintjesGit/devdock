<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Packages\Cqrs\Command;

/**
 * Writes the newest version of every item into the linked folder. This is
 * the one way a library reaches its folder, and it only happens by hand.
 *
 * @method static int execute(int $libraryId)
 */
class WriteToFolderAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
	) {}

	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): int {
		$libraryEntity = $libraryRepository->getById($this->libraryId);
		$written = 0;

		foreach ($libraryEntity?->items ?? [] as $itemEntity) {
			$folder = $itemEntity->kind === LibraryItemKindEnum::RULE ? $libraryEntity->folder : $libraryEntity->skillsFolder;
			$latest = $itemEntity->latest();

			if ($folder === null || $latest === null) {
				continue;
			}

			$libraryDiskRepository->writeFolder($folder, $itemEntity, $latest->contents);
			$written++;
		}

		return $written;
	}
}
