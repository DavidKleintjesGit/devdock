<?php

declare(strict_types=1);

namespace Src\Skill\Repositories;

use Carbon\CarbonImmutable;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Models\Skill as SkillEloquent;
use Src\Skill\Objects\SkillEntity;
use Src\Skill\Objects\SkillEntityCollection;
use Src\Skill\Objects\SkillId;
use Src\Skill\Objects\SkillObject;
use Src\Skill\Objects\SkillObjectCollection;

class SkillRepository implements SkillRepositoryContract
{
	public function create(SkillObject $skillObject): SkillEntity
	{
		$skillEloquent = SkillEloquent::query()->updateOrCreate(
			['path' => $skillObject->path],
			[
				'name' => $skillObject->name,
				'scope' => $skillObject->scope->value,
				'project' => $skillObject->project,
				'plugin' => $skillObject->plugin,
				'description' => $skillObject->description,
				'lines' => $skillObject->lines,
				'scanned_at' => CarbonImmutable::now(),
			],
		);

		return $this->mapToEntity($skillEloquent);
	}

	public function getAll(): SkillEntityCollection
	{
		$skillEntities = SkillEloquent::query()
			->orderBy('name')
			->get()
			->map(fn (SkillEloquent $skillEloquent) => $this->mapToEntity($skillEloquent))
			->all();

		return SkillEntityCollection::fromArray($skillEntities);
	}

	public function getById(SkillId $skillId): ?SkillEntity
	{
		$skillEloquent = SkillEloquent::query()->find($skillId->id);

		return $skillEloquent === null ? null : $this->mapToEntity($skillEloquent);
	}

	public function replaceAll(SkillObjectCollection $skillObjects): SkillEntityCollection
	{
		$paths = [];

		foreach ($skillObjects as $skillObject) {
			$this->create($skillObject);
			$paths[] = $skillObject->path;
		}

		if ($paths !== []) {
			SkillEloquent::query()->whereNotIn('path', $paths)->delete();
		}

		return $this->getAll();
	}

	private function mapToEntity(SkillEloquent $skillEloquent): SkillEntity
	{
		return new SkillEntity(
			skillId: new SkillId(id: $skillEloquent->id),
			name: (string) $skillEloquent->name,
			scope: SkillScopeEnum::from((string) $skillEloquent->scope),
			project: $skillEloquent->project,
			plugin: $skillEloquent->plugin,
			description: $skillEloquent->description,
			path: (string) $skillEloquent->path,
			lines: (int) $skillEloquent->lines,
			scannedAt: $skillEloquent->scanned_at,
		);
	}
}
