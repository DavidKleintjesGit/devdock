<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Objects\ContextFileObject;
use Src\Skill\Actions\GetSkillContentsAction;
use Src\Skill\Actions\GetSkillsAction;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Objects\SkillEntity;

/**
 * The skills the agent and every lens can call on: your own, the ones from
 * plugins, and those of the project. Without a project no project skills.
 *
 * @method static array<int, ContextFileObject> execute(?string $project)
 */
class GetSkillContextFilesAction extends Command
{
	public function __construct(
		public readonly ?string $project,
	) {}

	/**
	 * @return array<int, ContextFileObject>
	 */
	public function handle(): array
	{
		return collect(GetSkillsAction::execute()->toArray())
			->filter(fn (SkillEntity $skillEntity) => $skillEntity->scope !== SkillScopeEnum::PROJECT || $skillEntity->project === $this->project)
			->sortBy(fn (SkillEntity $skillEntity) => array_search($skillEntity->scope, SkillScopeEnum::cases(), true))
			->map(fn (SkillEntity $skillEntity) => new ContextFileObject(
				key: 'skill:' . $skillEntity->skillId->id,
				name: $skillEntity->name,
				group: $skillEntity->scope->label(),
				description: $skillEntity->description,
				path: $skillEntity->path,
				contents: GetSkillContentsAction::execute($skillEntity->skillId),
			))
			->values()
			->all();
	}
}
