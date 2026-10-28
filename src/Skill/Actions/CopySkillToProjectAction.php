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
 * Puts a skill down in another project. When a skill with this name already
 * stands there, those files go under.
 *
 * @method static bool execute(SkillId $skillId, string $project)
 */
class CopySkillToProjectAction extends Command
{
	public function __construct(
		public readonly SkillId $skillId,
		public readonly string $project,
	) {
	}

	public function handle(
		SkillRepositoryContract $skillRepository,
		SkillFileSystemRepositoryContract $skillFileSystemRepository,
	): bool {
		$skillEntity = $skillRepository->getById($this->skillId);

		$projectEntity = GetProjectsAction::execute()
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			->first();

		if ($skillEntity === null || $projectEntity === null) {
			return false;
		}

		if (! $skillFileSystemRepository->copyToProject($skillEntity, $projectEntity->path)) {
			return false;
		}

		ScanSkillsAction::execute();

		return true;
	}
}
