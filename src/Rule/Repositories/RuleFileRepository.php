<?php

declare(strict_types=1);

namespace Src\Rule\Repositories;

use Carbon\CarbonImmutable;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Enums\RuleOriginEnum;
use Src\Rule\Models\RuleFile as RuleFileEloquent;
use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileEntityCollection;
use Src\Rule\Objects\RuleFileId;
use Src\Rule\Objects\RuleFileObject;
use Src\Rule\Objects\RuleFileObjectCollection;

class RuleFileRepository implements RuleFileRepositoryContract
{
	public function create(RuleFileObject $ruleFileObject): RuleFileEntity
	{
		$ruleFileEloquent = RuleFileEloquent::query()->updateOrCreate(
			[
				'project' => $ruleFileObject->project,
				'path' => $ruleFileObject->path,
			],
			[
				'group' => $ruleFileObject->group->value,
				'origin' => $ruleFileObject->origin->value,
				'lines' => $ruleFileObject->lines,
				'words' => $ruleFileObject->words,
				'bytes' => $ruleFileObject->bytes,
				'core_file' => $ruleFileObject->coreFile,
				'core_lines' => $ruleFileObject->coreLines,
				'scanned_at' => CarbonImmutable::now(),
			],
		);

		return $this->mapToEntity($ruleFileEloquent);
	}

	public function getAll(): RuleFileEntityCollection
	{
		$ruleFileEntities = RuleFileEloquent::query()
			->orderBy('project')
			->orderBy('path')
			->get()
			->map(fn (RuleFileEloquent $ruleFileEloquent) => $this->mapToEntity($ruleFileEloquent))
			->all();

		return RuleFileEntityCollection::fromArray($ruleFileEntities);
	}

	public function getById(RuleFileId $ruleFileId): ?RuleFileEntity
	{
		$ruleFileEloquent = RuleFileEloquent::query()->find($ruleFileId->id);

		return $ruleFileEloquent === null ? null : $this->mapToEntity($ruleFileEloquent);
	}

	public function replaceAll(RuleFileObjectCollection $ruleFileObjects): RuleFileEntityCollection
	{
		$paths = [];

		foreach ($ruleFileObjects as $ruleFileObject) {
			$this->create($ruleFileObject);
			$paths[] = $ruleFileObject->project . '/' . $ruleFileObject->path;
		}

		if ($paths !== []) {
			RuleFileEloquent::query()->get()
				->reject(fn (RuleFileEloquent $ruleFileEloquent) => in_array(
					$ruleFileEloquent->project . '/' . $ruleFileEloquent->path,
					$paths,
					true,
				))
				->each(fn (RuleFileEloquent $ruleFileEloquent) => $ruleFileEloquent->delete());
		}

		return $this->getAll();
	}

	private function mapToEntity(RuleFileEloquent $ruleFileEloquent): RuleFileEntity
	{
		return new RuleFileEntity(
			ruleFileId: new RuleFileId(id: $ruleFileEloquent->id),
			project: (string) $ruleFileEloquent->project,
			path: (string) $ruleFileEloquent->path,
			group: RuleGroupEnum::from((string) $ruleFileEloquent->group),
			origin: RuleOriginEnum::from((string) $ruleFileEloquent->origin),
			lines: (int) $ruleFileEloquent->lines,
			words: (int) $ruleFileEloquent->words,
			bytes: (int) $ruleFileEloquent->bytes,
			coreFile: $ruleFileEloquent->core_file,
			coreLines: $ruleFileEloquent->core_lines === null ? null : (int) $ruleFileEloquent->core_lines,
			scannedAt: $ruleFileEloquent->scanned_at,
		);
	}
}
