<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Worktree\Actions\RemoveWorktreeAction;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Models\Worktree;
use Src\Worktree\Objects\WorktreeId;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see RemoveWorktreeAction
 */
class RemoveWorktreeActionTest extends TestCase
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
	public function execute_leaves_the_packages_of_a_folder_made_outside_devdock(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		$this->repository->write('.worktrees/feature-billing/vendor/autoload.php', '<?php');
		$worktree = Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
			'origin' => WorktreeOriginEnum::ADOPTED,
		]);

		RemoveWorktreeAction::execute(new WorktreeId(id: $worktree->id));

		$this->assertFileExists($path . '/vendor/autoload.php');
	}

	#[Test]
	public function execute_forgets_the_link_to_a_folder_made_outside_devdock(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		$worktree = Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
			'origin' => WorktreeOriginEnum::ADOPTED,
		]);

		RemoveWorktreeAction::execute(new WorktreeId(id: $worktree->id));

		$this->assertDatabaseMissing('worktrees', ['id' => $worktree->id]);
		$this->assertDirectoryExists($path);
	}

	#[Test]
	public function execute_removes_a_folder_git_already_let_go_of(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		// git took the working copy away, but its empty folder stayed behind
		$this->repository->git('worktree', 'remove', $path);
		mkdir($path);
		$worktree = Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
			'origin' => WorktreeOriginEnum::MADE,
		]);

		$removed = RemoveWorktreeAction::execute(new WorktreeId(id: $worktree->id));

		$this->assertTrue($removed);
		$this->assertDirectoryDoesNotExist($path);
		$this->assertDatabaseMissing('worktrees', ['id' => $worktree->id]);
	}

	#[Test]
	public function execute_keeps_a_folder_git_let_go_of_that_still_holds_files(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		$this->repository->git('worktree', 'remove', $path);
		$this->repository->write('.worktrees/feature-billing/Invoice.php', '<?php');
		$worktree = Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
			'origin' => WorktreeOriginEnum::MADE,
		]);

		$removed = RemoveWorktreeAction::execute(new WorktreeId(id: $worktree->id));

		$this->assertFalse($removed);
		$this->assertFileExists($path . '/Invoice.php');
		$this->assertDatabaseHas('worktrees', ['id' => $worktree->id]);
	}
}
