<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun;

class PromptRunFactory extends Factory
{
	protected $model = PromptRun::class;

	public function definition(): array
	{
		return [
			'card' => 'todo:' . fake()->numberBetween(1, 1000),
			'review_round_id' => null,
			'project' => fake()->slug(2),
			'branch' => null,
			'directory' => null,
			'ticket_key' => null,
			'session_uuid' => null,
			'claims_session' => false,
			'prompt' => fake()->sentence(),
			'status' => PromptStatusEnum::RUNNING->value,
			'output' => null,
			'message' => null,
			'cost' => null,
			'started_at' => now(),
			'finished_at' => null,
		];
	}
}
