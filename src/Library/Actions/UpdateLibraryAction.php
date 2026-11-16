<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRemoteRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryEntity;
use Src\Packages\Cqrs\Command;

/**
 * Renames a library or links it to another source: a folder on disk, or a
 * folder in a repository on Bitbucket or GitHub. The versions it holds stay as
 * they are; a repository it is newly linked to is read at once.
 *
 * @method static LibraryEntity execute(int $libraryId, string $name, ?string $source, ?string $skillsSource)
 */
class UpdateLibraryAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
		public readonly string $name,
		public readonly ?string $source,
		public readonly ?string $skillsSource,
	) {}

	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryRemoteRepositoryContract $libraryRemoteRepository,
	): LibraryEntity {
		$isAddress = fn (?string $source): bool => filled($source) && $libraryRemoteRepository->isAddress((string) $source);
		$folder = fn (?string $source): ?string => blank($source) || $isAddress($source) ? null : rtrim(str_replace('\\', '/', trim((string) $source)), '/');
		$address = fn (?string $source): ?string => $isAddress($source) ? rtrim(trim((string) $source), '/') : null;

		$libraryEntity = $libraryRepository->update(
			$this->libraryId,
			trim($this->name),
			$folder($this->source),
			$folder($this->skillsSource),
			$address($this->source),
			$address($this->skillsSource),
		);

		if ($libraryEntity->repository !== null || $libraryEntity->skillsRepository !== null) {
			ImportRepositoryAction::execute($libraryEntity->id);
		}

		return $libraryEntity;
	}
}
