<?php

declare(strict_types=1);

namespace Src\Skill\Actions;

use Src\Packages\Cqrs\Command;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Objects\SkillEntityCollection;

/**
 * @method static SkillEntityCollection execute()
 */
class GetSkillsAction extends Command
{
	public function handle(SkillRepositoryContract $skillRepository): SkillEntityCollection
	{
		return $skillRepository->getAll();
	}
}
