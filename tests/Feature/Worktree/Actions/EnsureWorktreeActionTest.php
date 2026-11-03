<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Worktree\Actions\EnsureWorktreeAction;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Models\Worktree;
use Src\Worktree\Objects\SiteName;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see EnsureWorktreeAction
 */
class EnsureWorktreeActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();

		// linking a Herd site and making databases reach outside the test
		$this->spy(WorkingCopyRepositoryContract::class);
		$this->spy(WorktreeDatabaseRepositoryContract::class);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_adds_a_worktree_on_a_local_branch(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');

		$worktreeEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->assertSame('feature/billing', $this->repository->branchIn($worktreeEntity->path));
	}

	#[Test]
	public function execute_places_the_worktree_in_the_worktrees_folder_of_the_project(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');

		$worktreeEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->assertSame($this->repository->path . '/.worktrees/feature-billing', $worktreeEntity->path);
	}

	#[Test]
	public function execute_cuts_a_new_branch_from_origin_main_without_an_upstream(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);

		EnsureWorktreeAction::execute($project->name, 'feature/new-invoice', null);

		$this->assertNull($this->repository->upstreamOf('feature/new-invoice'));
	}

	#[Test]
	public function execute_records_that_it_cut_the_branch_itself(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);

		EnsureWorktreeAction::execute($project->name, 'feature/new-invoice', null);

		$this->assertDatabaseHas('worktrees', [
			'project' => $project->name,
			'branch' => 'feature/new-invoice',
			'branch_created' => true,
		]);
	}

	#[Test]
	public function execute_tracks_a_branch_that_only_stands_on_origin(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');
		$this->repository->moveToOrigin('feature/billing');

		EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->assertSame('origin/feature/billing', $this->repository->upstreamOf('feature/billing'));
	}

	#[Test]
	public function execute_refuses_a_branch_that_is_checked_out_in_the_project_itself(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');
		$this->repository->checkout('feature/billing');

		$this->expectException(WorktreeNotPossibleException::class);

		EnsureWorktreeAction::execute($project->name, 'feature/billing', null);
	}

	#[Test]
	public function execute_refuses_a_project_that_is_not_on_disk(): void
	{
		$this->expectException(WorktreeNotPossibleException::class);

		EnsureWorktreeAction::execute(fake()->slug(2), 'feature/billing', null);
	}

	#[Test]
	public function execute_returns_the_standing_worktree_instead_of_adding_another(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');
		$worktreeEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$standingEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->assertSame($worktreeEntity->worktreeId->id, $standingEntity->worktreeId->id);
		$this->assertSame(1, Worktree::query()->count());
	}

	#[Test]
	public function execute_prepares_the_working_copy_under_a_site_of_its_own(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');

		$worktreeEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->app->make(WorkingCopyRepositoryContract::class)
			->shouldHaveReceived('prepare')
			->once()
			->with($this->repository->path, $worktreeEntity->path, Mockery::on(fn (SiteName $siteName): bool => $siteName == SiteName::for($project->name, 'feature/billing')));
	}

	#[Test]
	public function execute_gives_the_working_copy_a_test_database_of_its_own(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->branch('feature/billing');

		$worktreeEntity = EnsureWorktreeAction::execute($project->name, 'feature/billing', null);

		$this->app->make(WorktreeDatabaseRepositoryContract::class)
			->shouldHaveReceived('giveOwnTestDatabase')
			->once()
			->with($this->repository->path, $worktreeEntity->path, 'feature/billing');
	}
}
