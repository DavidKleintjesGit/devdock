<?php

declare(strict_types=1);

namespace Src\Library\Contracts;

use Carbon\CarbonImmutable;
use Src\Library\Enums\LandingEnum;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryVersionEntity;

interface LibraryRepositoryContract
{
	/**
	 * @return array<int, LibraryEntity>
	 */
	public function getAll(): array;

	public function getById(int $id): ?LibraryEntity;

	public function create(string $name, ?string $folder, ?string $skillsFolder): LibraryEntity;

	public function update(int $id, string $name, ?string $folder, ?string $skillsFolder): LibraryEntity;

	public function delete(int $id): void;

	/**
	 * Writes a new version of an item, making the item when it is new. The
	 * same content as the newest version is not a new version.
	 */
	public function addVersion(
		int $libraryId,
		LibraryItemKindEnum $kind,
		string $name,
		string $contents,
		VersionSourceEnum $source,
		?string $note = null,
		?string $project = null,
		?CarbonImmutable $createdAt = null,
	): LibraryVersionEntity;

	public function deleteItem(int $itemId): void;

	public function follow(int $libraryId, string $project): void;

	public function unfollow(int $libraryId, string $project): void;

	public function recordRollout(int $versionId, string $project, string $path, ?string $backup): void;

	/**
	 * Every roll-out of a version of this item, newest first.
	 *
	 * @return array<int, array{version: int, project: string, path: string, at: ?CarbonImmutable}>
	 */
	public function getRollouts(int $itemId): array;

	/**
	 * @return array{0: ?LandingEnum, 1: ?string}
	 */
	public function getLanding(string $project): array;

	public function setLanding(string $project, LandingEnum $landing, ?string $path): void;

	/**
	 * @return array<int, array{project: string, path: string}>
	 */
	public function getFavorites(LibraryItemKindEnum $kind): array;

	public function toggleFavorite(LibraryItemKindEnum $kind, string $project, string $path): bool;
}
