<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Access\Enums\GitGrantEnum;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Worktree\Actions\ResolveConflictsAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\RebaseOutcomeEnum;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see ResolveConflictsAction
 */
class ResolveConflictsActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private string $path;

	protected function setUp(): void
	{
		parent::setUp();

		// a working copy on its own branch, and a main line that moved on since
		$this->repository = TemporaryGitRepository::create();
		$this->repository->commit('billing/Invoice.php', "<?php\n// first\n", 'Add an invoice');
		$this->repository->git('push', '--quiet', 'origin', 'main');
		$this->path = $this->repository->addWorktree('feature/invoices');
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_leaves_a_conflicting_rebase_open_and_hands_the_agent_a_turn_with_the_rebase_grant(): void
	{
		$this->commitInWorkingCopy('billing/Invoice.php', "<?php\n// ours\n");
		$this->moveMainOn('billing/Invoice.php', "<?php\n// theirs\n");
		$this->mock(ClaudeProcessRepositoryContract::class)->shouldIgnoreMissing();

		$rebaseOutcomeObject = ResolveConflictsAction::execute('CSM-1', 'shop', 'feature/invoices', 'CSM-1', $this->path);

		$this->assertSame(RebaseOutcomeEnum::CONFLICTED, $rebaseOutcomeObject->outcome);
		$this->assertSame(['billing/Invoice.php'], $rebaseOutcomeObject->conflictedFiles);
		$this->assertTrue(resolve(WorktreeRepositoryContract::class)->isRebasing($this->path));
		$this->assertDatabaseHas('prompt_runs', ['card' => 'CSM-1', 'git_grant' => GitGrantEnum::REBASE->value]);
	}

	#[Test]
	public function execute_rebases_without_the_agent_when_nothing_clashes(): void
	{
		$this->commitInWorkingCopy('billing/Credit.php', "<?php\n");
		$this->moveMainOn('billing/Invoice.php', "<?php\n// theirs\n");

		$rebaseOutcomeObject = ResolveConflictsAction::execute('CSM-1', 'shop', 'feature/invoices', 'CSM-1', $this->path);

		$this->assertSame(RebaseOutcomeEnum::DONE, $rebaseOutcomeObject->outcome);
		$this->assertDatabaseMissing('prompt_runs', ['card' => 'CSM-1']);
	}

	#[Test]
	public function execute_refuses_while_work_is_not_committed(): void
	{
		$this->commitInWorkingCopy('billing/Invoice.php', "<?php\n// ours\n");
		$this->moveMainOn('billing/Invoice.php', "<?php\n// theirs\n");
		file_put_contents($this->path . '/billing/Credit.php', "<?php\n");

		$rebaseOutcomeObject = ResolveConflictsAction::execute('CSM-1', 'shop', 'feature/invoices', 'CSM-1', $this->path);

		$this->assertSame(RebaseOutcomeEnum::FAILED, $rebaseOutcomeObject->outcome);
		$this->assertFalse(resolve(WorktreeRepositoryContract::class)->isRebasing($this->path));
		$this->assertDatabaseMissing('prompt_runs', ['card' => 'CSM-1']);
	}

	private function commitInWorkingCopy(string $file, string $contents): void
	{
		@mkdir(dirname($this->path . '/' . $file), 0777, true);
		file_put_contents($this->path . '/' . $file, $contents);
		$this->repository->git('-C', $this->path, 'add', '.');
		$this->repository->git('-C', $this->path, 'commit', '--quiet', '-m', 'Work on ' . $file);
	}

	private function moveMainOn(string $file, string $contents): void
	{
		$this->repository->commit($file, $contents, 'Change ' . $file . ' on main');
		$this->repository->git('push', '--quiet', 'origin', 'main');
	}
}
