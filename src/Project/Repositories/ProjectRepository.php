<?php

declare(strict_types=1);

namespace Src\Project\Repositories;

use Carbon\CarbonImmutable;
use Src\Project\Contracts\ProjectRepositoryContract;
use Src\Project\Models\Project as ProjectEloquent;
use Src\Project\Objects\ProjectEntity;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Project\Objects\ProjectId;
use Src\Project\Objects\ProjectObject;
use Src\Project\Objects\ProjectObjectCollection;

class ProjectRepository implements ProjectRepositoryContract
{
	public function create(ProjectObject $projectObject): ProjectEntity
	{
		$projectEloquent = ProjectEloquent::query()->updateOrCreate(
			['name' => $projectObject->name],
			[
				'path' => $projectObject->path,
				'branch' => $projectObject->branch,
				'changed_files' => $projectObject->changedFiles,
				'ahead' => $projectObject->ahead,
				'behind' => $projectObject->behind,
				'remote' => $projectObject->remote,
				'scanned_at' => CarbonImmutable::now(),
			],
		);

		return $this->mapToEntity($projectEloquent);
	}

	public function getAll(): ProjectEntityCollection
	{
		$projectEntities = ProjectEloquent::query()
			->orderBy('name')
			->get()
			->map(fn (ProjectEloquent $projectEloquent) => $this->mapToEntity($projectEloquent))
			->all();

		return ProjectEntityCollection::fromArray($projectEntities);
	}

	public function getByName(string $name): ?ProjectEntity
	{
		$projectEloquent = ProjectEloquent::query()->where('name', $name)->first();

		return $projectEloquent === null ? null : $this->mapToEntity($projectEloquent);
	}

	public function replaceAll(ProjectObjectCollection $projectObjects): ProjectEntityCollection
	{
		$names = [];

		foreach ($projectObjects as $projectObject) {
			$this->create($projectObject);
			$names[] = $projectObject->name;
		}

		// an empty scan means the sites folder was unreachable, not that there are
		// no projects left
		if ($names !== []) {
			ProjectEloquent::query()->whereNotIn('name', $names)->delete();
		}

		return $this->getAll();
	}

	private function mapToEntity(ProjectEloquent $projectEloquent): ProjectEntity
	{
		return new ProjectEntity(
			projectId: new ProjectId(id: $projectEloquent->id),
			name: (string) $projectEloquent->name,
			path: (string) $projectEloquent->path,
			branch: $projectEloquent->branch,
			changedFiles: (int) $projectEloquent->changed_files,
			ahead: (int) $projectEloquent->ahead,
			behind: (int) $projectEloquent->behind,
			remote: $projectEloquent->remote,
			scannedAt: $projectEloquent->scanned_at,
		);
	}
}
