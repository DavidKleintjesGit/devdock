<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Worktree\Actions\GetRepairStateAction;
use Src\Worktree\Actions\RepairSetupAction;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see RepairSetupAction
 */
class RepairSetupActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		config(['devdock.herd.sites' => $this->repository->root . '/no-herd']);

		// a working copy whose branch follows the main line, which the repair takes away
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->path = $this->repository->addWorktree('feature/billing');
		$this->repository->git('branch', '--set-upstream-to=origin/main', 'feature/billing');
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
	public function execute_removes_an_upstream_on_the_main_line(): void
	{
		RepairSetupAction::execute($this->path, SetupCheckEnum::BRANCH);

		$this->assertNull($this->repository->upstreamOf('feature/billing'));
	}

	#[Test]
	public function execute_writes_what_the_step_ran_to_the_log(): void
	{
		$repairStateObject = RepairSetupAction::execute($this->path, SetupCheckEnum::BRANCH);

		$this->assertStringContainsString('git branch --unset-upstream', $repairStateObject->logTail());
	}

	#[Test]
	public function execute_leaves_the_repair_finished_without_a_failure(): void
	{
		RepairSetupAction::execute($this->path);

		$repairStateObject = GetRepairStateAction::execute($this->path);

		$this->assertFalse($repairStateObject->isRunning);
		$this->assertNull($repairStateObject->failure);
	}

	#[Test]
	public function execute_repairs_nothing_in_a_copy_that_is_gone(): void
	{
		$this->repository->git('worktree', 'remove', '--force', $this->path);
		Worktree::query()->update(['path' => $this->path]);

		$repairStateObject = RepairSetupAction::execute($this->path, SetupCheckEnum::BRANCH);

		// a folder that is gone has no points left to repair, so nothing ran and nothing failed
		$this->assertNull($repairStateObject->setupCheck);
	}
}
