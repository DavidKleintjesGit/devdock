<?php

declare(strict_types=1);

namespace Src\Skill\Contracts;

use Src\Skill\Objects\SkillEntity;
use Src\Skill\Objects\SkillEntityCollection;
use Src\Skill\Objects\SkillId;
use Src\Skill\Objects\SkillObject;
use Src\Skill\Objects\SkillObjectCollection;

interface SkillRepositoryContract
{
	public function create(SkillObject $skillObject): SkillEntity;

	public function getAll(): SkillEntityCollection;

	public function getById(SkillId $skillId): ?SkillEntity;

	/**
	 * Brings the stored skills in line with what the scan found.
	 */
	public function replaceAll(SkillObjectCollection $skillObjects): SkillEntityCollection;
}
