<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Review\Models\Lens;

class LensFactory extends Factory
{
	protected $model = Lens::class;

	public function definition(): array
	{
		return [
			'name' => fake()->unique()->slug(2),
			'description' => fake()->sentence(),
			'paths' => ['**'],
			'rules' => [],
			'command' => null,
			'position' => fake()->numberBetween(1, 100),
			'project' => null,
		];
	}
}
