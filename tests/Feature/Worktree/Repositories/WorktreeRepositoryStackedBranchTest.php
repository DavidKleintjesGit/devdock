<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Repositories\WorktreeRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorktreeRepository
 */
class WorktreeRepositoryStackedBranchTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a child pull request on top of a parent one, and the parent rewritten afterwards, as a rebase of the parent does
		$this->repository = TemporaryGitRepository::create();
		$this->repository->startBranch('feature/parent');
		$this->repository->commit('billing/Invoice.php', "<?php\n// one\n", 'Add an invoice');
		$this->repository->git('push', '--quiet', 'origin', 'feature/parent');
		$this->path = $this->repository->addWorktree('feature/child');
		file_put_contents($this->path . '/billing/Credit.php', "<?php\n");
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Add a credit');
		$this->repository->write('billing/Invoice.php', "<?php\n// uno\n");
		$this->repository->git('commit', '--quiet', '--all', '--amend', '--no-edit');
		$this->repository->git('push', '--quiet', '--force', 'origin', 'feature/parent');

		resolve(WorktreeRepositoryContract::class)->setTargetBranch($this->repository->path, 'feature/child', 'feature/parent');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_branch_changes_holds_only_the_own_work_of_a_branch_on_a_rewritten_parent(): void
	{
		$changeObjects = resolve(WorktreeRepositoryContract::class)->getBranchChanges($this->path);

		$this->assertSame(['billing/Credit.php'], collect($changeObjects)->map(fn (ChangeObject $changeObject): string => $changeObject->path)->all());
	}

	#[Test]
	public function rebase_moves_only_the_own_commits_onto_the_rewritten_parent(): void
	{
		$failure = resolve(WorktreeRepositoryContract::class)->rebase($this->path);

		$this->assertNull($failure);
		$this->assertSame('1', $this->repository->git('-C', $this->path, 'rev-list', '--count', 'origin/feature/parent..HEAD'));
		$this->assertSame("<?php\n// uno", $this->repository->git('-C', $this->path, 'show', 'HEAD:billing/Invoice.php'));
	}

	#[Test]
	public function rebase_leaves_main_line_commits_and_old_parent_copies_behind_when_git_forgot_the_fork(): void
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		// the base is noted while the child still holds its parent, before the parent is rewritten again
		$this->repository->git('-C', $this->path, 'reset', '--quiet', '--hard', 'origin/feature/parent');
		file_put_contents($this->path . '/billing/Credit.php', "<?php\n");
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Add a credit');
		$worktreeRepository->rememberBase($this->repository->path, 'feature/child');
		// the main line moves on and is merged into the child, as a hand merge does
		$this->repository->checkout('main');
		$this->repository->commit('billing/Tax.php', "<?php\n", 'Add tax on the main line');
		$this->repository->git('push', '--quiet', 'origin', 'main');
		$mainLineCommit = $this->repository->git('rev-parse', 'HEAD');
		$this->repository->git('-C', $this->path, 'merge', '--quiet', '--no-edit', 'origin/main');
		// the parent is rewritten once more, and git keeps no memory of its old tip
		$this->repository->checkout('feature/parent');
		$this->repository->write('billing/Invoice.php', "<?php\n// eins\n");
		$this->repository->git('commit', '--quiet', '--all', '--amend', '--no-edit');
		$this->repository->git('push', '--quiet', '--force', 'origin', 'feature/parent');
		$this->repository->git('reflog', 'expire', '--expire=now', '--all');

		$failure = $worktreeRepository->rebase($this->path);

		$this->assertNull($failure);
		$this->assertSame('1', $this->repository->git('-C', $this->path, 'rev-list', '--count', 'origin/feature/parent..HEAD'));
		$this->assertFalse($this->repository->gitSucceedsWith([], '-C', $this->path, 'merge-base', '--is-ancestor', $mainLineCommit, 'HEAD'));
	}

	#[Test]
	public function get_for_sees_no_conflict_where_only_the_old_copy_of_the_parent_would_clash(): void
	{
		$branchStandingObject = resolve(BranchStandingRepositoryContract::class)->getFor($this->repository->path, 'feature/child');

		$this->assertSame([], $branchStandingObject->conflictedFiles);
		$this->assertSame('feature/parent', $branchStandingObject->targetBranch);
	}
}
