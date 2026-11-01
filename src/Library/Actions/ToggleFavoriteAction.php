<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Packages\Cqrs\Command;

/**
 * Bookmarks a rule file or skill of a project, so it is close at hand when
 * you add something to another project. Nothing else follows from it.
 *
 * @method static bool execute(LibraryItemKindEnum $kind, string $project, string $path)
 */
class ToggleFavoriteAction extends Command
{
	public function __construct(
		public readonly LibraryItemKindEnum $kind,
		public readonly string $project,
		public readonly string $path,
	) {}

	public function handle(LibraryRepositoryContract $libraryRepository): bool
	{
		return $libraryRepository->toggleFavorite($this->kind, $this->project, $this->path);
	}
}
