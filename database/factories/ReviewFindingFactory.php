<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Models\Lens;
use Src\Review\Models\ReviewFinding;
use Src\Review\Models\ReviewRound;

class ReviewFindingFactory extends Factory
{
	protected $model = ReviewFinding::class;

	public function definition(): array
	{
		return [
			'card' => 'todo:' . fake()->numberBetween(1, 1000),
			'lens_id' => Lens::factory(),
			'number' => fake()->unique()->numberBetween(1, 100000),
			'location' => 'app/' . fake()->word() . '.php:' . fake()->numberBetween(1, 300),
			'text' => fake()->sentence(),
			'status' => FindingStatusEnum::OPEN->value,
			'reason' => null,
			'first_review_round_id' => ReviewRound::factory(),
			'last_review_round_id' => fn (array $attributes): int => $attributes['first_review_round_id'],
			'resolved_review_round_id' => null,
		];
	}
}
