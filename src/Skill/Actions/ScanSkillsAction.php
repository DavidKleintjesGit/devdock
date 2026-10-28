<?php

declare(strict_types=1);

namespace Src\Skill\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Objects\SkillEntityCollection;

/**
 * Looks up every skill on this machine and brings storage in line with it.
 *
 * @method static SkillEntityCollection execute()
 */
class ScanSkillsAction extends Command
{
	public function handle(
		SkillFileSystemRepositoryContract $skillFileSystemRepository,
		SkillRepositoryContract $skillRepository,
	): SkillEntityCollection {
		$skillObjects = $skillFileSystemRepository->getAll(GetProjectsAction::execute());

		return $skillRepository->replaceAll($skillObjects);
	}
}
