<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Objects\LibraryEntity;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;

/**
 * Makes a library, fills it from a folder when one is given, and lets the
 * projects that hold the same files follow it.
 *
 * @method static LibraryEntity execute(string $name, ?string $folder, ?string $skillsFolder, bool $keepLinked, ?array $followers = null)
 */
class CreateLibraryAction extends Command
{
	/**
	 * @param  array<int, string>|null  $followers  null lets DevDock pick the projects with matching files
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $folder,
		public readonly ?string $skillsFolder,
		public readonly bool $keepLinked,
		public readonly ?array $followers = null,
	) {}

	public function handle(LibraryRepositoryContract $libraryRepository): LibraryEntity
	{
		$folder = $this->cleaned($this->folder);
		$skillsFolder = $this->cleaned($this->skillsFolder);

		$libraryEntity = $libraryRepository->create($this->name, $this->keepLinked ? $folder : null, $this->keepLinked ? $skillsFolder : null);

		ImportFolderAction::execute($libraryEntity->id, $folder, $skillsFolder, true);

		// the project the folder lives in is the source, not a follower
		$source = GetProjectsAction::execute()
			->filter(fn (ProjectEntity $projectEntity) => $folder !== null
				&& str_starts_with($folder . '/', rtrim(str_replace('\\', '/', $projectEntity->path), '/') . '/'))
			->pluck('name')
			->all();

		$followers = $this->followers ?? array_diff(array_keys(array_filter(
			FindMatchingProjectsAction::execute($libraryEntity->id),
			fn (int $matches) => $matches > 0,
		)), $source);

		foreach ($followers as $project) {
			$libraryRepository->follow($libraryEntity->id, $project);
		}

		return $libraryRepository->getById($libraryEntity->id);
	}

	private function cleaned(?string $folder): ?string
	{
		return blank($folder) ? null : rtrim(str_replace('\\', '/', trim((string) $folder)), '/');
	}
}
