<?php

declare(strict_types=1);

namespace Src\Library\Actions;

use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Enums\LandingEnum;
use Src\Packages\Cqrs\Command;

/**
 * Where library rules land in a project: what you chose, otherwise the place
 * that already exists there, otherwise .claude/rules.
 *
 * @method static array{0: LandingEnum, 1: ?string, 2: bool} execute(string $project, string $projectPath)
 */
class GetLandingAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly string $projectPath,
	) {}

	/**
	 * @return array{0: LandingEnum, 1: ?string, 2: bool} the landing, its folder, and whether it was chosen
	 */
	public function handle(
		LibraryRepositoryContract $libraryRepository,
		LibraryDiskRepositoryContract $libraryDiskRepository,
	): array {
		[$landing, $folder] = $libraryRepository->getLanding($this->project);

		if ($landing !== null) {
			return [$landing, $folder, true];
		}

		$existing = $libraryDiskRepository->landingsIn($this->projectPath);

		foreach ([LandingEnum::CLAUDE, LandingEnum::CURSOR, LandingEnum::AGENTS] as $candidate) {
			if ($existing[$candidate->value]) {
				return [$candidate, null, false];
			}
		}

		return [LandingEnum::CLAUDE, null, false];
	}
}
