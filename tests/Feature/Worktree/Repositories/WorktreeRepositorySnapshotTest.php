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
class WorktreeRepositorySnapshotTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		$this->repository->commit('billing/Invoice.php', "<?php\n// first\n", 'Add an invoice');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_changes_between_two_snapshots_holds_only_what_changed_in_between(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		// work that was already there before the turn
		$this->repository->write('billing/Draft.php', '<?php');
		$before = (string) $worktreeRepository->snapshot($this->repository->path);
		$this->repository->write('billing/Invoice.php', "<?php\n// second\n");
		$this->repository->write('billing/Credit.php', '<?php');
		$after = (string) $worktreeRepository->snapshot($this->repository->path);

		$changeObjects = $worktreeRepository->getChangesBetween($this->repository->path, $before, $after);

		$this->assertEqualsCanonicalizing(
			['billing/Credit.php', 'billing/Invoice.php'],
			collect($changeObjects)->map(fn (ChangeObject $changeObject): string => $changeObject->path)->all(),
		);
	}

	#[Test]
	public function snapshot_leaves_the_index_of_the_working_copy_alone(): void
	{
		$this->repository->write('billing/Credit.php', '<?php');
		$statusBefore = $this->repository->git('status', '--porcelain');

		resolve(WorktreeRepositoryContract::class)->snapshot($this->repository->path);

		$this->assertSame($statusBefore, $this->repository->git('status', '--porcelain'));
	}

	#[Test]
	public function get_diff_between_two_snapshots_shows_the_lines_of_that_turn(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$before = (string) $worktreeRepository->snapshot($this->repository->path);
		$this->repository->write('billing/Invoice.php', "<?php\n// second\n");
		$after = (string) $worktreeRepository->snapshot($this->repository->path);

		$patch = (string) $worktreeRepository->getDiffBetween($this->repository->path, 'billing/Invoice.php', $before, $after);

		$this->assertStringContainsString('-// first', $patch);
		$this->assertStringContainsString('+// second', $patch);
	}
}
