<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryEntity;
use Src\Packages\Cqrs\Command;

/**
 * Renames a library or links it to another folder. The versions it holds
 * stay as they are.
 *
 * @method static LibraryEntity execute(int $libraryId, string $name, ?string $folder, ?string $skillsFolder)
 */
class UpdateLibraryAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
		public readonly string $name,
		public readonly ?string $folder,
		public readonly ?string $skillsFolder,
	) {}

	public function handle(LibraryRepositoryContract $libraryRepository): LibraryEntity
	{
		$clean = fn (?string $folder) => blank($folder) ? null : rtrim(str_replace('\\', '/', trim((string) $folder)), '/');

		return $libraryRepository->update($this->libraryId, trim($this->name), $clean($this->folder), $clean($this->skillsFolder));
	}
}
