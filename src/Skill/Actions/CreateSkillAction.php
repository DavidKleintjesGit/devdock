<?php

declare(strict_types=1);

namespace Src\Skill\Actions;

use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Enums\SkillScopeEnum;

/**
 * Lays down a new skill, globally or in a project.
 *
 * @method static ?string execute(string $name, string $description, string $where)
 */
class CreateSkillAction extends Command
{
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly string $where,
	) {
	}

	public function handle(SkillFileSystemRepositoryContract $skillFileSystemRepository): ?string
	{
		$projectPath = $this->where === SkillScopeEnum::GLOBALLY->value
			? null
			: $this->projectPath();

		if ($this->where !== SkillScopeEnum::GLOBALLY->value && $projectPath === null) {
			return null;
		}

		$path = $skillFileSystemRepository->createSkill($this->name, $this->description, $projectPath);

		if ($path !== null) {
			ScanSkillsAction::execute();
		}

		return $path;
	}

	private function projectPath(): ?string
	{
		return GetProjectsAction::execute()
			->filter(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->where)
			->first()?->path;
	}
}
