<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Board\Models\Todo;

class TodoFactory extends Factory
{
	protected $model = Todo::class;

	public function definition(): array
	{
		return [
			'title' => fake()->sentence(4),
			'note' => fake()->optional()->paragraph(),
			'project' => fake()->slug(2),
			'is_later' => false,
			'done_at' => null,
		];
	}
}
