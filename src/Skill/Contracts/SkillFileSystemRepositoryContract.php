<?php

declare(strict_types=1);

namespace Src\Skill\Contracts;

use Src\Project\Objects\ProjectEntityCollection;
use Src\Skill\Objects\SkillEntity;
use Src\Skill\Objects\SkillObjectCollection;

interface SkillFileSystemRepositoryContract
{
	/**
	 * Every skill on this machine: global, in one of the projects, or from an
	 * installed plugin.
	 */
	public function getAll(ProjectEntityCollection $projectEntities): SkillObjectCollection;

	public function getContents(SkillEntity $skillEntity): ?string;

	/**
	 * How many files already stand where the skill would go; zero means nothing
	 * is overwritten.
	 */
	public function fileCountInPlace(SkillEntity $skillEntity, string $projectPath): int;

	/**
	 * Puts the whole skill folder down in a project; the reference material next
	 * to SKILL.md belongs with it.
	 */
	public function copyToProject(SkillEntity $skillEntity, string $projectPath): bool;

	/**
	 * Lays down a new SKILL.md and gives back the path. When the folder already
	 * exists, nothing happens.
	 */
	public function createSkill(string $name, string $description, ?string $projectPath): ?string;
}
