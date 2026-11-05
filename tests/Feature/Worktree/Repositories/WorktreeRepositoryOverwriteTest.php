<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Repositories;

use PHPUnit\Framework\Attributes\Test;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Repositories\WorktreeRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see WorktreeRepository
 */
class WorktreeRepositoryOverwriteTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a branch on origin whose commit was rebased onto a main line that moved on
		$this->repository = TemporaryGitRepository::create();
		$this->path = $this->repository->addWorktree('feature/billing');
		file_put_contents($this->path . '/Invoice.php', '<?php');
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Add an invoice');
		$this->repository->git('-C', $this->path, 'push', '--quiet', '-u', 'origin', 'feature/billing');
		$this->repository->commit('README.md', 'moved on', 'Move the main line on');
		$this->repository->git('push', '--quiet', 'origin', 'main');
		$this->repository->git('-C', $this->path, 'rebase', '--quiet', 'main');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function get_upstream_commits_lost_by_overwrite_is_empty_after_a_rebase(): void
	{
		$subjects = resolve(WorktreeRepositoryContract::class)->getUpstreamCommitsLostByOverwrite($this->path);

		$this->assertSame([], $subjects);
	}

	#[Test]
	public function get_upstream_commits_lost_by_overwrite_names_a_commit_someone_else_pushed(): void
	{
		$otherPath = $this->repository->root . '/other';
		$this->repository->git('clone', '--quiet', '--branch', 'feature/billing', $this->repository->originPath, $otherPath);
		file_put_contents($otherPath . '/Credit.php', '<?php');
		$this->repository->git('-C', $otherPath, 'add', '.');
		$this->repository->git('-C', $otherPath, '-c', 'user.name=Colleague', '-c', 'user.email=colleague@example.com', 'commit', '--quiet', '-m', 'Add a credit');
		$this->repository->git('-C', $otherPath, 'push', '--quiet', 'origin', 'feature/billing');
		$this->repository->git('-C', $this->path, 'fetch', '--quiet', 'origin');

		$subjects = resolve(WorktreeRepositoryContract::class)->getUpstreamCommitsLostByOverwrite($this->path);

		$this->assertSame(['Add a credit'], $subjects);
	}
}
