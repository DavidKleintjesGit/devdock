<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Repositories\WorktreeDatabaseRepository;
use Tests\TestCase;

/**
 * @see WorktreeDatabaseRepository
 */
class WorktreeDatabaseRepositoryTest extends TestCase
{
	private string $projectPath;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		$root = sys_get_temp_dir() . '/devdock-tests/database-' . Str::random(12);
		$this->projectPath = $root . '/project';
		$this->path = $root . '/project/.worktrees/feature-billing';
		File::ensureDirectoryExists($this->path);
		File::put($this->projectPath . '/.env.testing', "DB_CONNECTION=mysql\nDB_DATABASE=test_shop\n");
	}

	protected function tearDown(): void
	{
		File::deleteDirectory(dirname($this->projectPath));

		parent::tearDown();
	}

	#[Test]
	public function own_test_database_names_the_one_devdock_made_for_the_copy(): void
	{
		File::put($this->path . '/.env.testing', "DB_CONNECTION=mysql\nDB_DATABASE=test_shop_billing\n");

		$name = resolve(WorktreeDatabaseRepositoryContract::class)->ownTestDatabase($this->projectPath, $this->path);

		$this->assertSame('test_shop_billing', $name);
	}

	#[Test]
	public function own_test_database_leaves_out_the_test_database_of_the_project(): void
	{
		File::put($this->path . '/.env.testing', "DB_CONNECTION=mysql\nDB_DATABASE=test_shop\n");

		$name = resolve(WorktreeDatabaseRepositoryContract::class)->ownTestDatabase($this->projectPath, $this->path);

		$this->assertNull($name);
	}

	#[Test]
	public function own_test_database_leaves_out_a_database_devdock_did_not_name(): void
	{
		File::put($this->path . '/.env.testing', "DB_CONNECTION=mysql\nDB_DATABASE=someone_elses\n");

		$name = resolve(WorktreeDatabaseRepositoryContract::class)->ownTestDatabase($this->projectPath, $this->path);

		$this->assertNull($name);
	}
}
