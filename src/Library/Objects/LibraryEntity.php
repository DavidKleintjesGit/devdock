<?php

declare(strict_types=1);

namespace Src\Library\Objects;

use Src\Library\Enums\LibraryItemKindEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * A set of rules and skills DevDock keeps with all their versions. It leads:
 * every project that follows it is compared with it.
 */
class LibraryEntity extends ValueObject
{
	/**
	 * @param  array<int, LibraryItemEntity>  $items
	 * @param  array<int, string>  $followers
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly ?string $folder,
		public readonly ?string $skillsFolder,
		public readonly ?string $repository,
		public readonly ?string $skillsRepository,
		public readonly array $items,
		public readonly array $followers,
	) {
		parent::__construct();
	}

	/**
	 * Where the rules come in from: the repository when there is one, otherwise the linked folder.
	 */
	public function source(): ?string
	{
		return $this->repository ?? $this->folder;
	}

	/**
	 * Where the skills come in from, the same way.
	 */
	public function skillsSource(): ?string
	{
		return $this->skillsRepository ?? $this->skillsFolder;
	}

	/**
	 * @return array<int, LibraryItemEntity>
	 */
	public function rules(): array
	{
		return array_values(array_filter($this->items, fn (LibraryItemEntity $itemEntity) => $itemEntity->kind === LibraryItemKindEnum::RULE));
	}

	/**
	 * @return array<int, LibraryItemEntity>
	 */
	public function skills(): array
	{
		return array_values(array_filter($this->items, fn (LibraryItemEntity $itemEntity) => $itemEntity->kind === LibraryItemKindEnum::SKILL));
	}

	/**
	 * The names of its rules without extension, in one line.
	 */
	public function ruleNames(): string
	{
		return implode(', ', array_map(fn (LibraryItemEntity $itemEntity) => $itemEntity->slug(), $this->rules()));
	}

	public function isFollowedBy(string $project): bool
	{
		return in_array($project, $this->followers, true);
	}

	public function ruleVersionCount(): int
	{
		return $this->versionCount(LibraryItemKindEnum::RULE);
	}

	public function versionCount(LibraryItemKindEnum $kind): int
	{
		return array_sum(array_map(
			fn (LibraryItemEntity $itemEntity) => $itemEntity->kind === $kind ? count($itemEntity->versions) : 0,
			$this->items,
		));
	}

	public function item(LibraryItemKindEnum $kind, string $name): ?LibraryItemEntity
	{
		foreach ($this->items as $itemEntity) {
			if ($itemEntity->kind === $kind && $itemEntity->name === $name) {
				return $itemEntity;
			}
		}

		return null;
	}
}
