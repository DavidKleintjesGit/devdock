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
			->where(fn ($query) => $query->whereNull('project')->when($project !== null, fn ($inner) => $inner->orWhere('project', $project)))
			->orderBy('position')
			->orderBy('name')
			->get()
			->map(function (LensEloquent $lensEloquent) use ($settings): LensEntity {
				$setting = $settings->get($lensEloquent->id);
				$standardPaths = (array) $lensEloquent->paths;
				$standardRules = (array) ($lensEloquent->rules ?? []);

				return new LensEntity(
					lensId: new LensId(id: $lensEloquent->id),
					name: (string) $lensEloquent->name,
					description: (string) $lensEloquent->description,
					paths: $setting?->paths ?? $standardPaths,
					command: $setting?->command === null ? $lensEloquent->command : ($setting->command === '' ? null : $setting->command),
					isEnabled: $setting?->is_enabled ?? true,
					rules: $setting?->rules ?? $standardRules,
					project: $lensEloquent->project,
					standardPaths: $standardPaths,
					standardRules: $standardRules,
					standardCommand: $lensEloquent->command,
					hasPickedRules: $setting?->rules !== null,
				);
			})
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

	public function customize(LensId $lensId, string $project, ?array $paths, ?array $rules, ?string $command): void
	{
		LensSettingEloquent::query()->updateOrCreate(
			['lens_id' => $lensId->id, 'project' => $project],
			['paths' => $paths, 'rules' => $rules, 'command' => $command],
		);
	}

	public function create(string $name, string $description, array $paths, array $rules, ?string $command, ?string $project): LensId
	{
		$lensEloquent = LensEloquent::query()->create([
			'name' => $name,
			'description' => $description,
			'paths' => array_values($paths),
			'rules' => array_values($rules),
			'command' => blank($command) ? null : $command,
			'project' => $project,
			'position' => ((int) LensEloquent::query()->max('position')) + 1,
		]);

		return new LensId(id: $lensEloquent->id);
	}

	public function updateStandard(LensId $lensId, string $description, array $paths, array $rules, ?string $command): void
	{
		LensEloquent::query()->whereKey($lensId->id)->update([
			'description' => $description,
			'paths' => json_encode(array_values($paths)),
			'rules' => json_encode(array_values($rules)),
			'command' => blank($command) ? null : $command,
		]);
	}

	public function delete(LensId $lensId): void
	{
		LensEloquent::query()->whereKey($lensId->id)->delete();
	}
}
