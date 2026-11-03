<?php

declare(strict_types=1);

namespace Tests\Feature\Prompt\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Board\Models\Todo;
use Src\Project\Models\Project;
use Src\Prompt\Actions\StartPromptAction;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Objects\PromptRunObject;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see StartPromptAction
 */
class StartPromptActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		config(['devdock.herd.sites' => $this->repository->root . '/no-herd']);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_writes_a_failed_turn_when_the_copy_is_not_ready(): void
	{
		$todo = Todo::factory()->create();
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		// the copy stands on another branch than the task's
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/invoices',
			'path' => $path,
		]);
		$this->spy(ClaudeProcessRepositoryContract::class);

		$promptRunEntity = StartPromptAction::execute(PromptRunObject::from([
			'card' => 'todo:' . $todo->id,
			'project' => $project->name,
			'branch' => 'feature/invoices',
			'prompt' => fake()->sentence(),
		]));

		$this->assertSame(PromptStatusEnum::FAILED, $promptRunEntity->status);
		$this->app->make(ClaudeProcessRepositoryContract::class)->shouldNotHaveReceived('startDetached');
	}
}
