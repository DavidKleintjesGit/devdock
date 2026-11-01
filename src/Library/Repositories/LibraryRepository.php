<?php

declare(strict_types=1);

namespace Src\Library\Repositories;

use Carbon\CarbonImmutable;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LandingEnum;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Enums\VersionSourceEnum;
use Src\Library\Models\Favorite as FavoriteEloquent;
use Src\Library\Models\Library as LibraryEloquent;
use Src\Library\Models\LibraryFollower as LibraryFollowerEloquent;
use Src\Library\Models\LibraryItem as LibraryItemEloquent;
use Src\Library\Models\LibraryRollout as LibraryRolloutEloquent;
use Src\Library\Models\LibraryVersion as LibraryVersionEloquent;
use Src\Library\Models\RuleLanding as RuleLandingEloquent;
use Src\Library\Objects\LibraryEntity;
use Src\Library\Objects\LibraryItemEntity;
use Src\Library\Objects\LibraryVersionEntity;

class LibraryRepository implements LibraryRepositoryContract
{
	public function getAll(): array
	{
		return LibraryEloquent::query()
			->with(['items.versions', 'followers'])
			->orderBy('id')
			->get()
			->map(fn (LibraryEloquent $libraryEloquent) => $this->mapToEntity($libraryEloquent))
			->all();
	}

	public function getById(int $id): ?LibraryEntity
	{
		$libraryEloquent = LibraryEloquent::query()
			->with(['items.versions', 'followers'])
			->find($id);

		return $libraryEloquent === null ? null : $this->mapToEntity($libraryEloquent);
	}

	public function create(string $name, ?string $folder, ?string $skillsFolder): LibraryEntity
	{
		$libraryEloquent = LibraryEloquent::query()->create([
			'name' => $name,
			'folder' => $folder,
			'skills_folder' => $skillsFolder,
		]);

		return $this->getById($libraryEloquent->id);
	}

	public function update(int $id, string $name, ?string $folder, ?string $skillsFolder): LibraryEntity
	{
		LibraryEloquent::query()->whereKey($id)->update([
			'name' => $name,
			'folder' => $folder,
			'skills_folder' => $skillsFolder,
		]);

		return $this->getById($id);
	}

	public function delete(int $id): void
	{
		LibraryEloquent::query()->whereKey($id)->delete();
	}

	public function addVersion(
		int $libraryId,
		LibraryItemKindEnum $kind,
		string $name,
		string $contents,
		VersionSourceEnum $source,
		?string $note = null,
		?string $project = null,
		?CarbonImmutable $createdAt = null,
	): LibraryVersionEntity {
		$itemEloquent = LibraryItemEloquent::query()->firstOrCreate([
			'library_id' => $libraryId,
			'kind' => $kind->value,
			'name' => $name,
		]);

		$hash = LibraryVersionEntity::hashOf($contents);
		$latest = LibraryVersionEloquent::query()
			->where('library_item_id', $itemEloquent->id)
			->orderByDesc('number')
			->first();

		if ($latest !== null && $latest->hash === $hash) {
			return $this->mapVersion($latest);
		}

		$versionEloquent = LibraryVersionEloquent::query()->create([
			'library_item_id' => $itemEloquent->id,
			'number' => ((int) $latest?->number) + 1,
			'contents' => $contents,
			'hash' => $hash,
			'source' => $source->value,
			'note' => $note === null ? null : mb_substr($note, 0, 250),
			'project' => $project,
			'created_at' => $createdAt ?? CarbonImmutable::now(),
		]);

		return $this->mapVersion($versionEloquent);
	}

	public function deleteItem(int $itemId): void
	{
		LibraryItemEloquent::query()->whereKey($itemId)->delete();
	}

	public function follow(int $libraryId, string $project): void
	{
		LibraryFollowerEloquent::query()->firstOrCreate(['library_id' => $libraryId, 'project' => $project]);
	}

	public function unfollow(int $libraryId, string $project): void
	{
		LibraryFollowerEloquent::query()
			->where('library_id', $libraryId)
			->where('project', $project)
			->delete();
	}

	public function recordRollout(int $versionId, string $project, string $path, ?string $backup): void
	{
		LibraryRolloutEloquent::query()->create([
			'library_version_id' => $versionId,
			'project' => $project,
			'path' => $path,
			'backup' => $backup,
			'created_at' => CarbonImmutable::now(),
		]);
	}

	public function getRollouts(int $itemId): array
	{
		return LibraryRolloutEloquent::query()
			->with(['version'])
			->whereHas('version', fn ($query) => $query->where('library_item_id', $itemId))
			->orderByDesc('id')
			->get()
			->map(fn (LibraryRolloutEloquent $rolloutEloquent) => [
				'version' => (int) $rolloutEloquent->version?->number,
				'project' => (string) $rolloutEloquent->project,
				'path' => (string) $rolloutEloquent->path,
				'at' => $rolloutEloquent->created_at,
			])
			->all();
	}

	public function getLanding(string $project): array
	{
		$landingEloquent = RuleLandingEloquent::query()->where('project', $project)->first();

		return $landingEloquent === null
			? [null, null]
			: [LandingEnum::tryFrom((string) $landingEloquent->kind), $landingEloquent->path];
	}

	public function setLanding(string $project, LandingEnum $landing, ?string $path): void
	{
		RuleLandingEloquent::query()->updateOrCreate(
			['project' => $project],
			['kind' => $landing->value, 'path' => $landing === LandingEnum::CUSTOM ? $path : null],
		);
	}

	public function getFavorites(LibraryItemKindEnum $kind): array
	{
		return FavoriteEloquent::query()
			->where('kind', $kind->value)
			->orderBy('project')
			->orderBy('path')
			->get()
			->map(fn (FavoriteEloquent $favoriteEloquent) => [
				'project' => (string) $favoriteEloquent->project,
				'path' => (string) $favoriteEloquent->path,
			])
			->all();
	}

	public function toggleFavorite(LibraryItemKindEnum $kind, string $project, string $path): bool
	{
		$favoriteEloquent = FavoriteEloquent::query()
			->where('kind', $kind->value)
			->where('project', $project)
			->where('path', $path)
			->first();

		if ($favoriteEloquent !== null) {
			$favoriteEloquent->delete();

			return false;
		}

		FavoriteEloquent::query()->create(['kind' => $kind->value, 'project' => $project, 'path' => $path]);

		return true;
	}

	private function mapToEntity(LibraryEloquent $libraryEloquent): LibraryEntity
	{
		return new LibraryEntity(
			id: (int) $libraryEloquent->id,
			name: (string) $libraryEloquent->name,
			folder: $libraryEloquent->folder,
			skillsFolder: $libraryEloquent->skills_folder,
			items: $libraryEloquent->items
				->sortBy('name')
				->map(fn (LibraryItemEloquent $itemEloquent) => new LibraryItemEntity(
					id: (int) $itemEloquent->id,
					libraryId: (int) $libraryEloquent->id,
					library: (string) $libraryEloquent->name,
					kind: LibraryItemKindEnum::from((string) $itemEloquent->kind),
					name: (string) $itemEloquent->name,
					versions: $itemEloquent->versions
						->map(fn (LibraryVersionEloquent $versionEloquent) => $this->mapVersion($versionEloquent))
						->values()
						->all(),
				))
				->values()
				->all(),
			followers: $libraryEloquent->followers->pluck('project')->sort()->values()->all(),
		);
	}

	private function mapVersion(LibraryVersionEloquent $versionEloquent): LibraryVersionEntity
	{
		return new LibraryVersionEntity(
			id: (int) $versionEloquent->id,
			number: (int) $versionEloquent->number,
			contents: (string) $versionEloquent->contents,
			hash: (string) $versionEloquent->hash,
			source: VersionSourceEnum::from((string) $versionEloquent->source),
			note: $versionEloquent->note,
			project: $versionEloquent->project,
			createdAt: $versionEloquent->created_at,
		);
	}
}
