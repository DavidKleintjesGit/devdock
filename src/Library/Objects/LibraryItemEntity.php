<?php

declare(strict_types=1);

namespace Src\Library\Objects;

use Src\Library\Enums\LibraryItemKindEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * A rule or skill in a library, with every version it ever had.
 */
class LibraryItemEntity extends ValueObject
{
	/**
	 * @param  array<int, LibraryVersionEntity>  $versions  oldest first
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $libraryId,
		public readonly string $library,
		public readonly LibraryItemKindEnum $kind,
		public readonly string $name,
		public readonly array $versions,
	) {
		parent::__construct();
	}

	public function latest(): ?LibraryVersionEntity
	{
		return $this->versions === [] ? null : $this->versions[array_key_last($this->versions)];
	}

	/**
	 * The version with exactly this content, newest first when two match.
	 */
	public function versionWithHash(string $hash): ?LibraryVersionEntity
	{
		foreach (array_reverse($this->versions) as $versionEntity) {
			if ($versionEntity->hash === $hash) {
				return $versionEntity;
			}
		}

		return null;
	}

	public function versionNumbered(int $number): ?LibraryVersionEntity
	{
		foreach ($this->versions as $versionEntity) {
			if ($versionEntity->number === $number) {
				return $versionEntity;
			}
		}

		return null;
	}

	/**
	 * The name without its extension, as a marker in AGENTS.md uses it.
	 */
	public function slug(): string
	{
		return pathinfo($this->name, PATHINFO_FILENAME);
	}
}
