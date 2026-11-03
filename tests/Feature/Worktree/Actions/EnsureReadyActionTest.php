<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Models\ReviewRound;
use Src\Worktree\Actions\EnsureReadyAction;
use Src\Worktree\Enums\WorkKindEnum;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see EnsureReadyAction
 */
class EnsureReadyActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		config(['devdock.herd.sites' => $this->repository->root . '/no-herd']);

		// a working copy of DevDock that passes its checklist
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->path = $this->repository->addWorktree('feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $this->path,
		]);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_lets_an_agent_turn_start_in_a_ready_copy(): void
	{
		$readinessObject = EnsureReadyAction::execute($this->path, WorkKindEnum::AGENT_TURN);

		$this->assertTrue($readinessObject->isReady());
	}

	#[Test]
	public function execute_refuses_work_in_a_folder_that_is_no_working_copy(): void
	{
		$this->expectException(SetupIncompleteException::class);

		EnsureReadyAction::execute($this->repository->path, WorkKindEnum::AGENT_TURN);
	}

	#[Test]
	public function execute_refuses_work_without_a_folder(): void
	{
		$this->expectException(SetupIncompleteException::class);

		EnsureReadyAction::execute(null, WorkKindEnum::TERMINAL);
	}

	#[Test]
	public function execute_refuses_an_agent_turn_while_another_turn_runs_in_the_copy(): void
	{
		PromptRun::factory()->create([
			'directory' => $this->path,
			'status' => PromptStatusEnum::RUNNING->value,
			'started_at' => now(),
		]);

		$this->expectException(WorkingCopyBusyException::class);

		EnsureReadyAction::execute($this->path, WorkKindEnum::AGENT_TURN);
	}

	#[Test]
	public function execute_refuses_an_agent_turn_while_the_lenses_look_in_the_copy(): void
	{
		ReviewRound::factory()->create([
			'directory' => $this->path,
			'status' => RoundStatusEnum::RUNNING->value,
			'started_at' => now(),
			'finished_at' => null,
		]);

		$this->expectException(WorkingCopyBusyException::class);

		EnsureReadyAction::execute($this->path, WorkKindEnum::AGENT_TURN);
	}

	#[Test]
	public function execute_lets_a_lens_run_beside_the_other_lenses_of_its_round(): void
	{
		ReviewRound::factory()->create([
			'directory' => $this->path,
			'status' => RoundStatusEnum::RUNNING->value,
			'started_at' => now(),
			'finished_at' => null,
		]);

		$readinessObject = EnsureReadyAction::execute($this->path, WorkKindEnum::LENS);

		$this->assertTrue($readinessObject->isReady());
	}

	#[Test]
	public function execute_ignores_a_turn_that_started_longer_ago_than_a_turn_may_run(): void
	{
		$datetimeLongAgo = now()->subSeconds((int) config('devdock.prompt_timeout') + 60);
		PromptRun::factory()->create([
			'directory' => $this->path,
			'status' => PromptStatusEnum::RUNNING->value,
			'started_at' => $datetimeLongAgo,
		]);

		$readinessObject = EnsureReadyAction::execute($this->path, WorkKindEnum::ROUND);

		$this->assertTrue($readinessObject->isReady());
	}
}
