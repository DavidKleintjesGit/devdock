<?php

declare(strict_types=1);

namespace Src\Review\Repositories;

use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Models\Lens as LensEloquent;
use Src\Review\Models\LensSetting as LensSettingEloquent;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\LensEntityCollection;
use Src\Review\Objects\LensId;

class LensRepository implements LensRepositoryContract
{
	public function getAll(?string $project = null): LensEntityCollection
	{
		$settings = $project === null
			? collect()
			: LensSettingEloquent::query()->where('project', $project)->get()->keyBy('lens_id');

		$lensEntities = LensEloquent::query()
			->orderBy('position')
			->orderBy('name')
			->get()
			->map(fn (LensEloquent $lensEloquent) => new LensEntity(
				lensId: new LensId(id: $lensEloquent->id),
				name: (string) $lensEloquent->name,
				description: (string) $lensEloquent->description,
				paths: (array) $lensEloquent->paths,
				command: $lensEloquent->command,
				isEnabled: $settings->get($lensEloquent->id)?->is_enabled ?? true,
			))
			->all();

		return LensEntityCollection::fromArray($lensEntities);
	}

	public function toggle(LensId $lensId, string $project, bool $isEnabled): void
	{
		LensSettingEloquent::query()->updateOrCreate(
			['lens_id' => $lensId->id, 'project' => $project],
			['is_enabled' => $isEnabled],
		);
	}
}
