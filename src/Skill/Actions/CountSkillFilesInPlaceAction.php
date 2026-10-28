<?php

declare(strict_types=1);

namespace Src\Skill\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Objects\SkillId;

/**
 * How many files already stand under this skill name in the target project;
 * those go under on a copy.
 *
 * @method static int execute(SkillId $skillId, string $project)
 */
class CountSkillFilesInPlaceAction extends Command
{
	public function __construct(
		public readonly SkillId $skillId,
		public readonly string $project,
	) {
	}

	public function handle(
		SkillRepositoryContract $skillRepository,
		SkillFileSystemRepositoryContract $skillFileSystemRepository,
	): int {
		$skillEntity = $skillRepository->getById($this->skillId);

		$projectEntity = GetProjectsAction::execute()
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			->first();

		if ($skillEntity === null || $projectEntity === null) {
			return 0;
		}

		return $skillFileSystemRepository->fileCountInPlace($skillEntity, $projectEntity->path);
	}
}
