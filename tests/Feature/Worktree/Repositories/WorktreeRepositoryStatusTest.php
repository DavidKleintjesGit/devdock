<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Models\Worktree;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Repositories\WorktreeRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorktreeRepository
 */
class WorktreeRepositoryStatusTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_all_reads_every_copy_against_the_branch_it_merges_into(): void
	{
		// a parent branch on origin that moved on, a child stacked on it and a copy on its own beside them
		$this->repository = TemporaryGitRepository::create();
		$this->repository->startBranch('feature/parent');
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->repository->git('push', '--quiet', 'origin', 'feature/parent');
		$childPath = $this->repository->addWorktree('feature/child');
		$this->repository->commit('billing/Credit.php', '<?php', 'Add a credit');
		$this->repository->git('push', '--quiet', 'origin', 'feature/parent');
		$this->repository->checkout('main');
		$loosePath = $this->repository->addWorktree('feature/loose');
		file_put_contents($loosePath . '/notes.md', 'draft');
		resolve(WorktreeRepositoryContract::class)->setTargetBranch($this->repository->path, 'feature/child', 'feature/parent');
		Worktree::factory()->create(['branch' => 'feature/child', 'path' => $childPath]);
		Worktree::factory()->create(['branch' => 'feature/loose', 'path' => $loosePath]);

		$worktreeEntities = collect(resolve(WorktreeRepositoryContract::class)->getAll())->keyBy(fn (WorktreeEntity $worktreeEntity): string => $worktreeEntity->branch);

		$this->assertSame('feature/parent', $worktreeEntities['feature/child']->targetBranch);
		$this->assertSame(1, $worktreeEntities['feature/child']->behind);
		$this->assertSame('main', $worktreeEntities['feature/loose']->targetBranch);
		$this->assertSame(1, $worktreeEntities['feature/loose']->changedFiles);
	}
}
