<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryEntity;
use Src\Packages\Cqrs\Command;

/**
 * Every library with its items, versions and followers. The first time,
 * the folder that used to be "core" becomes a library of its own.
 *
 * @method static array<int, LibraryEntity> execute()
 */
class GetLibrariesAction extends Command
{
	/**
	 * @return array<int, LibraryEntity>
	 */
	public function handle(LibraryRepositoryContract $libraryRepository): array
	{
		$libraryEntities = $libraryRepository->getAll();

		if ($libraryEntities === [] && is_dir($this->coreFolder())) {
			CreateLibraryAction::execute(basename((string) config('devdock.paths.core')), $this->coreFolder(), null, true);

			return $libraryRepository->getAll();
		}

		return $libraryEntities;
	}

	private function coreFolder(): string
	{
		return rtrim(str_replace('\\', '/', (string) config('devdock.paths.core')), '/') . '/ai/instructions';
	}
}
