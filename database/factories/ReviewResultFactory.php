<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\Lens;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;

class ReviewResultFactory extends Factory
{
	protected $model = ReviewResult::class;

	public function definition(): array
	{
		return [
			'review_round_id' => ReviewRound::factory(),
			'lens_id' => Lens::factory(),
			'verdict' => VerdictEnum::PASS->value,
			'findings' => 0,
			'report' => null,
			'touched_files' => fake()->numberBetween(1, 10),
			'finished_at' => now()->subMinutes(30),
		];
	}
}
