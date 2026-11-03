<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Project\Models\Project;

class ProjectFactory extends Factory
{
	protected $model = Project::class;

	public function definition(): array
	{
		return [
			'name' => fake()->unique()->slug(2),
			'path' => sys_get_temp_dir() . '/' . fake()->uuid(),
			'branch' => 'main',
			'changed_files' => 0,
			'ahead' => 0,
			'behind' => 0,
			'remote' => null,
			'scanned_at' => now(),
		];
	}
}
