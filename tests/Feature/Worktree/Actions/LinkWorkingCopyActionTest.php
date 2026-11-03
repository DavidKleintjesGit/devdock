<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Packages\Process\DetachedCommand;
use Src\Project\Models\Project;
use Src\Worktree\Actions\LinkWorkingCopyAction;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see LinkWorkingCopyAction
 */
class LinkWorkingCopyActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		config(['devdock.herd.sites' => $this->repository->root . '/no-herd']);

		// linking a Herd site, writing the trust of Claude Code and making databases reach outside the test
		$this->spy(WorkingCopyRepositoryContract::class);
		$this->spy(WorktreeDatabaseRepositoryContract::class);
		$this->spy(DetachedCommand::class);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_makes_a_working_copy_of_its_own_on_a_new_branch(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);

		LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->assertSame('feature/billing', $this->repository->branchIn($this->repository->path . '/.worktrees/feature-billing'));
	}

	#[Test]
	public function execute_links_the_task_to_the_branch(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$card = 'todo:' . fake()->numberBetween(1, 100);

		LinkWorkingCopyAction::execute($card, $project->name, 'feature/billing');

		$this->assertDatabaseHas('task_links', [
			'kind' => 'branch',
			'value' => 'feature/billing',
			'is_active' => true,
		]);
	}

	#[Test]
	public function execute_puts_the_project_back_on_its_main_line_when_the_branch_is_open_there(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->startBranch('feature/billing');

		LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->assertSame('main', $this->repository->branchIn($this->repository->path));
	}

	#[Test]
	public function execute_refuses_while_the_project_holds_work_that_is_not_committed(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->startBranch('feature/billing');
		$this->repository->write('README.md', fake()->sentence());

		$failure = LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->assertNotNull($failure);
		$this->assertSame('feature/billing', $this->repository->branchIn($this->repository->path));
	}

	#[Test]
	public function execute_refuses_a_branch_checked_out_in_a_folder_outside_devdock(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->git('worktree', 'add', '--quiet', '-b', 'feature/billing', $this->repository->root . '/elsewhere');

		$failure = LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->assertStringContainsString('elsewhere', (string) $failure);
	}

	#[Test]
	public function execute_replaces_a_link_to_the_project_itself_with_a_working_copy_of_its_own(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $this->repository->path,
			'origin' => WorktreeOriginEnum::PROJECT,
		]);

		LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->assertDatabaseHas('worktrees', [
			'branch' => 'feature/billing',
			'origin' => WorktreeOriginEnum::MADE->value,
		]);
		$this->assertDatabaseMissing('worktrees', ['origin' => WorktreeOriginEnum::PROJECT->value]);
	}

	#[Test]
	public function execute_starts_repairing_a_copy_that_is_not_complete(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		// the project carries a file the new copy does not have yet
		$this->repository->write('CLAUDE.local.md', fake()->sentence());

		LinkWorkingCopyAction::execute('todo:' . fake()->numberBetween(1, 100), $project->name, 'feature/billing');

		$this->app->make(DetachedCommand::class)->shouldHaveReceived('artisan')->withArgs(fn (string $command): bool => str_starts_with($command, 'devdock:repair'));
	}
}
