<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryEntity;
use Src\Packages\Cqrs\Command;

/**
 * Brings every library that lives in a repository up to date with it, the
 * way a fetch round brings in the tickets and pull requests. Returns how many
 * versions came in.
 *
 * @method static int execute()
 */
class ImportLibrariesAction extends Command
{
	public function handle(LibraryRepositoryContract $libraryRepository): int
	{
		return (int) collect($libraryRepository->getAll())
			->filter(fn (LibraryEntity $libraryEntity): bool => $libraryEntity->repository !== null || $libraryEntity->skillsRepository !== null)
			->sum(fn (LibraryEntity $libraryEntity): int => ImportRepositoryAction::execute($libraryEntity->id));
	}
}
