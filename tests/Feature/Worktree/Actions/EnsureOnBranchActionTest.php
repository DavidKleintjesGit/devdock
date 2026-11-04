<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Actions\EnsureOnBranchAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see EnsureOnBranchAction
 */
class EnsureOnBranchActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a working copy whose rebase on main stopped on a conflict
		$this->repository = TemporaryGitRepository::create();
		$this->repository->commit('billing/Invoice.php', "<?php\n// first\n", 'Add an invoice');
		$this->path = $this->repository->addWorktree('feature/invoices');
		file_put_contents($this->path . '/billing/Invoice.php', "<?php\n// ours\n");
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '--all', '-m', 'Change the invoice');
		$this->repository->commit('billing/Invoice.php', "<?php\n// theirs\n", 'Change the invoice on main');
		$this->repository->gitSucceedsWith([], '-C', $this->path, 'rebase', 'main');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_accepts_the_open_rebase_of_the_branch_for_the_turn_that_finishes_it(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);

		// throws when the open rebase is not taken for standing on the branch
		EnsureOnBranchAction::execute($this->path, 'feature/invoices', acceptsRebase: true);

		$this->assertNull($worktreeRepository->getBranch($this->path));
		$this->assertSame('feature/invoices', $worktreeRepository->getRebasingBranch($this->path));
	}

	#[Test]
	public function execute_refuses_a_copy_on_no_branch_for_any_other_work(): void
	{
		$this->expectException(WorktreeNotPossibleException::class);

		EnsureOnBranchAction::execute($this->path, 'feature/invoices');
	}
}
