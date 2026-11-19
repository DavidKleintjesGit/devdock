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
class WorktreeRepositoryRebasedBranchTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a branch whose base was noted on an older stand of main, then rebased onto main after main moved on
		$this->repository = TemporaryGitRepository::create();
		$this->path = $this->repository->addWorktree('feature/reminder');
		file_put_contents($this->path . '/Reminder.php', "<?php\n");
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Add a reminder');
		resolve(WorktreeRepositoryContract::class)->rememberBase($this->repository->path, 'feature/reminder');
		$this->repository->checkout('main');
		$this->repository->commit('Tax.php', "<?php\n", 'Add tax on the main line');
		$this->repository->git('push', '--quiet', 'origin', 'main');
		$this->repository->git('-C', $this->path, 'fetch', '--quiet', 'origin');
		$this->repository->git('-C', $this->path, 'rebase', '--quiet', 'origin/main');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_branch_changes_leaves_out_what_main_brought_in_after_the_remembered_base(): void
	{
		$changeObjects = resolve(WorktreeRepositoryContract::class)->getBranchChanges($this->path);

		// Tax.php came from main after the base was noted; only the reminder is the branch's own
		$this->assertSame(['Reminder.php'], collect($changeObjects)->map(fn (ChangeObject $changeObject): string => $changeObject->path)->all());
	}
}
