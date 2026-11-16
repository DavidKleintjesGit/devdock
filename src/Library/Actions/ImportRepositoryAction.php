<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRemoteRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Packages\Cqrs\Command;

/**
 * Brings what changed in a library's repository on Bitbucket or GitHub into
 * the library as new versions. A file that did not change adds nothing.
 *
 * @method static int execute(int $libraryId)
 */
class ImportRepositoryAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
	) {}

	/**
	 * Returns how many versions came in.
	 */
	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryRemoteRepositoryContract $libraryRemoteRepository,
	): int {
		$libraryEntity = $libraryRepository->getById($this->libraryId);

		if ($libraryEntity === null) {
			return 0;
		}

		$added = 0;
		$sources = array_filter([
			LibraryItemKindEnum::RULE->value => $libraryEntity->repository,
			LibraryItemKindEnum::SKILL->value => $libraryEntity->skillsRepository,
		]);

		foreach ($sources as $kind => $address) {
			$itemKind = LibraryItemKindEnum::from($kind);

			foreach ($libraryRemoteRepository->readFolder($address, $itemKind) as $name => $contents) {
				$before = $libraryEntity->item($itemKind, $name)?->latest()?->number ?? 0;
				$versionEntity = $libraryRepository->addVersion($this->libraryId, $itemKind, $name, $contents, VersionSourceEnum::REPOSITORY);

				$added += max(0, $versionEntity->number - $before);
			}
		}

		return $added;
	}
}
