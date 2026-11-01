<?php

declare(strict_types=1);

namespace Src\Library\Contracts;

use Carbon\CarbonImmutable;
use Src\Library\Enums\LandingEnum;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Objects\LibraryItemEntity;

interface LibraryDiskRepositoryContract
{
	/**
	 * What a folder holds now: rule files by name, or skills by folder name
	 * with all their files packed as one content.
	 *
	 * @return array<string, string>
	 */
	public function readFolder(string $folder, LibraryItemKindEnum $kind): array;

	/**
	 * Every earlier content of a rule file in a folder that git keeps, oldest
	 * first, with the commit message and moment. Empty outside git.
	 *
	 * @return array<int, array{contents: string, note: string, at: CarbonImmutable}>
	 */
	public function historyOf(string $folder, string $name): array;

	/**
	 * Writes the content of an item into the linked folder. Only ever on a
	 * press of the button.
	 */
	public function writeFolder(string $folder, LibraryItemEntity $item, string $contents): void;

	/**
	 * What stands in the project for this item, in the form the library keeps
	 * it: a block of AGENTS.md without its markers, a Cursor file without its
	 * header. Null when it is not there.
	 */
	public function readProjectItem(string $projectPath, LibraryItemEntity $item, LandingEnum $landing, ?string $folder): ?string;

	/**
	 * Puts the content down in the project and returns what stood there
	 * before, so it can be kept.
	 */
	public function writeProjectItem(string $projectPath, LibraryItemEntity $item, LandingEnum $landing, ?string $folder, string $contents): ?string;

	/**
	 * Where rules already land in this project, from what stands on disk.
	 *
	 * @return array<string, bool> per landing whether its folder or file exists
	 */
	public function landingsIn(string $projectPath): array;

	/**
	 * Every rule file or skill in a project under the usual places, by name,
	 * for finding which projects match a library.
	 *
	 * @return array<string, string>
	 */
	public function projectFiles(string $projectPath, LibraryItemKindEnum $kind): array;
}
