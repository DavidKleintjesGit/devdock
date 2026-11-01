<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LandingEnum;
use Src\Packages\Cqrs\Command;

/**
 * Sets where library rules land in a project from now on. What already
 * stands somewhere else stays where it is.
 *
 * @method static void execute(string $project, LandingEnum $landing, ?string $folder)
 */
class SetLandingAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly LandingEnum $landing,
		public readonly ?string $folder,
	) {}

	public function handle(LibraryRepositoryContract $libraryRepository): void
	{
		$folder = blank($this->folder) ? null : trim(str_replace('\\', '/', (string) $this->folder), '/');

		$libraryRepository->setLanding($this->project, $this->landing, $folder);
	}
}
