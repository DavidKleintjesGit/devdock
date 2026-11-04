<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Worktree\Actions\CheckReadinessAction;
use Src\Worktree\Enums\CheckStateEnum;
use Src\Worktree\Enums\SetupCheckEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see CheckReadinessAction
 */
class CheckReadinessActionTest extends TestCase
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
	public function execute_holds_back_a_folder_that_is_no_working_copy_of_devdock(): void
	{
		$readinessObject = CheckReadinessAction::execute($this->repository->path);

		$this->assertSame(CheckStateEnum::OPEN, collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::WORKING_COPY)->state);
	}

	#[Test]
	public function execute_holds_back_the_checkout_of_the_project_itself(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'main',
			'path' => $this->repository->path,
			'origin' => WorktreeOriginEnum::PROJECT,
		]);

		$readinessObject = CheckReadinessAction::execute($this->repository->path);

		$this->assertFalse($readinessObject->isReady());
	}

	#[Test]
	public function execute_holds_back_a_folder_made_outside_devdock(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
			'origin' => WorktreeOriginEnum::ADOPTED,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertFalse($readinessObject->isReady());
	}

	#[Test]
	public function execute_passes_a_complete_working_copy(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertTrue($readinessObject->isReady());
	}

	#[Test]
	public function execute_opens_the_own_files_point_when_the_exclude_lacks_them(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		file_put_contents($this->repository->path . '/.git/info/exclude', '');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertSame(CheckStateEnum::OPEN, collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::OWN_FILES_OUT_OF_GIT)->state);
	}

	#[Test]
	public function execute_opens_the_php_packages_point_when_a_borrowed_package_is_missing(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->commit('composer.json', '{}', 'Add composer');
		$this->repository->write('vendor/autoload.php', '<?php');
		$this->repository->write('vendor/acme/tool/Tool.php', '<?php');
		$this->repository->write('vendor/blade-ui-kit/icons/Icons.php', '<?php');
		$path = $this->repository->addWorktree('feature/billing');
		// the copy borrows one package of the two, as a borrow that was broken off leaves it
		mkdir($path . '/vendor');
		file_put_contents($path . '/vendor/autoload.php', '<?php');
		$this->repository->borrow($this->repository->path . '/vendor/acme', $path . '/vendor/acme');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertStringContainsString('blade-ui-kit', collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::PHP_PACKAGES)->detail);
	}

	#[Test]
	public function execute_opens_the_branch_point_when_the_copy_stands_on_another_branch(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/invoices',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertSame(CheckStateEnum::OPEN, collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::BRANCH)->state);
	}

	#[Test]
	public function execute_offers_to_remove_an_upstream_on_the_main_line(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		$this->repository->git('branch', '--set-upstream-to=origin/main', 'feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertTrue(collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::BRANCH)->canRepair());
	}

	#[Test]
	public function execute_opens_the_carried_files_point_for_a_file_the_copy_lacks(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$path = $this->repository->addWorktree('feature/billing');
		$this->repository->write('CLAUDE.local.md', fake()->sentence());
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertStringContainsString('CLAUDE.local.md', collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::CARRIED_FILES)->detail);
	}

	#[Test]
	public function execute_opens_the_own_database_point_when_the_copy_shares_the_database_of_the_project(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$database = fake()->slug(1);
		$this->repository->commit('composer.json', '{"require":{"laravel/framework":"^13.0"}}', 'Make it Laravel');
		$this->repository->write('.env', "DB_CONNECTION=mysql\nDB_DATABASE={$database}\n");
		$path = $this->repository->addWorktree('feature/billing');
		file_put_contents($path . '/.env', "DB_CONNECTION=mysql\nDB_DATABASE={$database}\n");
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertSame(CheckStateEnum::OPEN, collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::OWN_DATABASE)->state);
	}

	#[Test]
	public function execute_lets_the_assets_wait_for_the_js_packages(): void
	{
		$project = Project::factory()->create(['path' => $this->repository->path]);
		$this->repository->commit('composer.json', '{"require":{"laravel/framework":"^13.0"}}', 'Make it Laravel');
		$this->repository->commit('webpack.mix.js', '', 'Build with Mix');
		$this->repository->commit('package.json', '{}', 'Add packages');
		$path = $this->repository->addWorktree('feature/billing');
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $path,
		]);

		$readinessObject = CheckReadinessAction::execute($path);

		$this->assertSame(CheckStateEnum::WAITING, collect($readinessObject->setupChecks)->firstWhere('setupCheck', SetupCheckEnum::ASSETS)->state);
	}
}
