<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Exceptions\NotAWorkingCopyException;
use Src\Worktree\Objects\SiteName;
use Src\Worktree\Repositories\WorkingCopyRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorkingCopyRepository
 */
class WorkingCopyRepositoryTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		config(['devdock.herd.sites' => null]);
		$this->repository = TemporaryGitRepository::create();
		$this->repository->write('vendor/autoload.php', '<?php');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function release_refuses_the_checkout_of_the_project_itself_and_keeps_its_vendor(): void
	{
		try {
			resolve(WorkingCopyRepositoryContract::class)->release($this->repository->path, null);
			$this->fail('A checkout of the project itself was released.');
		} catch (NotAWorkingCopyException) {
			$this->assertFileExists($this->repository->path . '/vendor/autoload.php');
		}
	}

	#[Test]
	public function return_borrowed_refuses_the_checkout_of_the_project_itself(): void
	{
		$this->expectException(NotAWorkingCopyException::class);

		resolve(WorkingCopyRepositoryContract::class)->returnBorrowed($this->repository->path, 'vendor');
	}

	#[Test]
	public function prepare_refuses_the_checkout_of_the_project_itself(): void
	{
		$this->expectException(NotAWorkingCopyException::class);

		resolve(WorkingCopyRepositoryContract::class)->prepare($this->repository->path, $this->repository->path, new SiteName('shop'));
	}

	#[Test]
	public function release_takes_the_borrowed_vendor_out_of_a_working_copy_and_leaves_the_project_its_own(): void
	{
		$path = $this->repository->addWorktree('feature/invoices');
		$this->repository->borrow($this->repository->path . '/vendor', $path . '/vendor');

		resolve(WorkingCopyRepositoryContract::class)->release($path, null);

		$this->assertDirectoryDoesNotExist($path . '/vendor');
		$this->assertFileExists($this->repository->path . '/vendor/autoload.php');
	}
}
