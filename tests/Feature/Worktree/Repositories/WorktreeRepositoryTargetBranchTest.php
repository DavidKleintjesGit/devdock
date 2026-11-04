<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Repositories\WorktreeRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorktreeRepository
 */
class WorktreeRepositoryTargetBranchTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a parent branch on origin with work of its own, and a child branch cut from it in a working copy
		$this->repository = TemporaryGitRepository::create();
		$this->repository->startBranch('feature/parent');
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->repository->git('push', '--quiet', 'origin', 'feature/parent');
		$this->path = $this->repository->addWorktree('feature/child');
		file_put_contents($this->path . '/billing/Credit.php', '<?php');
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Add a credit');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_branch_changes_against_the_main_line_holds_the_work_of_the_parent_too(): void
	{
		$changeObjects = resolve(WorktreeRepositoryContract::class)->getBranchChanges($this->path);

		$this->assertEqualsCanonicalizing(
			['billing/Credit.php', 'billing/Invoice.php'],
			collect($changeObjects)->map(fn (ChangeObject $changeObject): string => $changeObject->path)->all(),
		);
	}

	#[Test]
	public function get_branch_changes_against_the_target_holds_only_the_own_work(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);

		$worktreeRepository->setTargetBranch($this->repository->path, 'feature/child', 'feature/parent');
		$worktreeRepository->forgetStatus($this->path);

		$this->assertSame(
			['billing/Credit.php'],
			collect($worktreeRepository->getBranchChanges($this->path))->map(fn (ChangeObject $changeObject): string => $changeObject->path)->all(),
		);
	}

	#[Test]
	public function set_target_branch_writes_nothing_down_for_the_main_line(): void
	{
		$isChanged = resolve(WorktreeRepositoryContract::class)->setTargetBranch($this->repository->path, 'feature/child', 'main');

		$this->assertFalse($isChanged);
		$this->assertFalse($this->repository->gitSucceedsWith([], 'config', '--get', 'branch.feature/child.devdock-target'));
	}

	#[Test]
	public function set_target_branch_takes_the_mark_away_again(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$worktreeRepository->setTargetBranch($this->repository->path, 'feature/child', 'feature/parent');

		$isChanged = $worktreeRepository->setTargetBranch($this->repository->path, 'feature/child', null);

		$this->assertTrue($isChanged);
		$this->assertFalse($this->repository->gitSucceedsWith([], 'config', '--get', 'branch.feature/child.devdock-target'));
	}
}
