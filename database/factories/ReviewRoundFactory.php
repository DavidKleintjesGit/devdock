<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Models\ReviewRound;

class ReviewRoundFactory extends Factory
{
	protected $model = ReviewRound::class;

	public function definition(): array
	{
		return [
			'card' => 'todo:' . fake()->numberBetween(1, 1000),
			'number' => 1,
			'series_start' => 1,
			'commit_sha' => fake()->sha1(),
			'fingerprint' => fake()->md5(),
			'directory' => null,
			'status' => RoundStatusEnum::PASSED->value,
			'started_at' => now()->subHour(),
			'finished_at' => now()->subMinutes(30),
		];
	}
}
