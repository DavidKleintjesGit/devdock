<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\PullRequest\Models\PullRequest;
use Src\Worktree\Actions\GetBranchChainAction;
use Src\Worktree\Objects\BranchLinkObject;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see GetBranchChainAction
 */
class GetBranchChainActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		// main → CSM-1 → CSM-1-variant, each with a pull request onto the one below
		$this->repository = TemporaryGitRepository::create();
		$this->repository->startBranch('feature/CSM-1');
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->repository->git('push', '--quiet', 'origin', 'feature/CSM-1');
		$this->repository->startBranch('feature/CSM-1-variant');
		$this->repository->commit('billing/Credit.php', '<?php', 'Add a credit');
		$this->repository->git('push', '--quiet', 'origin', 'feature/CSM-1-variant');
		PullRequest::factory()->create(['repository' => 'shop', 'source_branch' => 'feature/CSM-1', 'destination_branch' => 'main']);
		PullRequest::factory()->create(['repository' => 'shop', 'source_branch' => 'feature/CSM-1-variant', 'destination_branch' => 'feature/CSM-1']);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_follows_the_stack_down_to_the_main_line(): void
	{
		$branchChainObject = GetBranchChainAction::execute($this->repository->path, 'shop', 'feature/CSM-1-variant', 'main');

		$this->assertSame(
			['feature/CSM-1-variant → feature/CSM-1', 'feature/CSM-1 → main'],
			array_map(fn (BranchLinkObject $branchLinkObject): string => $branchLinkObject->branch . ' → ' . $branchLinkObject->target, $branchChainObject->links),
		);
		$this->assertSame('CSM-1', $branchChainObject->links[1]->cardKey);
		$this->assertNull($branchChainObject->blockingLink());
	}

	#[Test]
	public function execute_blocks_on_the_link_below_that_is_behind_its_own_target(): void
	{
		$this->repository->checkout('main');
		$this->repository->commit('billing/Tax.php', '<?php', 'Add tax');
		$this->repository->git('push', '--quiet', 'origin', 'main');

		$branchChainObject = GetBranchChainAction::execute($this->repository->path, 'shop', 'feature/CSM-1-variant', 'main');

		$this->assertSame('feature/CSM-1', $branchChainObject->blockingLink()?->branch);
		$this->assertSame(1, $branchChainObject->blockingLink()?->behind);
	}

	#[Test]
	public function execute_does_not_block_a_branch_on_its_own_backlog(): void
	{
		$this->repository->checkout('feature/CSM-1');
		$this->repository->commit('billing/Discount.php', '<?php', 'Add a discount');
		$this->repository->git('push', '--quiet', 'origin', 'feature/CSM-1');

		$branchChainObject = GetBranchChainAction::execute($this->repository->path, 'shop', 'feature/CSM-1-variant', 'main');

		// the variant is behind its own target, which its own rebase solves
		$this->assertSame(1, $branchChainObject->links[0]->behind);
		$this->assertNull($branchChainObject->blockingLink());
	}
}
