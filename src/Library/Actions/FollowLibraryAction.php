<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Packages\Cqrs\Command;

/**
 * Lets a project follow a library, or stop following it. Following only
 * means comparing; nothing is written.
 *
 * @method static void execute(int $libraryId, string $project, bool $isFollowing)
 */
class FollowLibraryAction extends Command
{
	public function __construct(
		public readonly int $libraryId,
		public readonly string $project,
		public readonly bool $isFollowing,
	) {}

	public function handle(LibraryRepositoryContract $libraryRepository): void
	{
		$this->isFollowing
			? $libraryRepository->follow($this->libraryId, $this->project)
			: $libraryRepository->unfollow($this->libraryId, $this->project);
	}
}
