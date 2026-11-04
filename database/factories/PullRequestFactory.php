<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\PullRequest\Enums\PullRequestStateEnum;
use Src\PullRequest\Models\PullRequest;

class PullRequestFactory extends Factory
{
	protected $model = PullRequest::class;

	public function definition(): array
	{
		return [
			'repository' => fake()->slug(2),
			'number' => fake()->unique()->numberBetween(1, 9999),
			'title' => fake()->sentence(),
			'state' => PullRequestStateEnum::OPEN->value,
			'draft' => false,
			'author' => fake()->name(),
			'my_role' => null,
			'source_branch' => 'feature/' . fake()->slug(2),
			'destination_branch' => 'main',
			'approvals' => 0,
			'reviewers' => 0,
			'changes_requested_by' => null,
			'comment_count' => 0,
			'url' => fake()->url(),
			'changed_at' => now(),
		];
	}

	public function merged(): self
	{
		return $this->state(['state' => PullRequestStateEnum::MERGED->value]);
	}
}
