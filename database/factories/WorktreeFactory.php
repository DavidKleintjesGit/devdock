<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Models\Worktree;

class WorktreeFactory extends Factory
{
	protected $model = Worktree::class;

	public function definition(): array
	{
		return [
			'project' => fake()->slug(2),
			'branch' => 'feature/' . fake()->slug(2),
			'branch_created' => false,
			'origin' => WorktreeOriginEnum::MADE,
			'path' => sys_get_temp_dir() . '/' . fake()->uuid(),
			'site' => null,
			'database' => null,
			'card' => null,
		];
	}
}
