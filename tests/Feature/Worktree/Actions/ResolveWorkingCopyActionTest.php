<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Worktree\Actions\ResolveWorkingCopyAction;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see ResolveWorkingCopyAction
 */
class ResolveWorkingCopyActionTest extends TestCase
{
	#[Test]
	public function execute_refuses_a_branch_that_is_open_in_the_project_itself(): void
	{
		$repository = TemporaryGitRepository::create();
		$project = Project::factory()->create(['path' => $repository->path]);
		$repository->startBranch('feature/billing');

		try {
			ResolveWorkingCopyAction::execute($project->name, 'feature/billing', null);
			$refusal = null;
		} catch (WorktreeNotPossibleException $exception) {
			$refusal = $exception;
		}

		$repository->remove();
		$this->assertInstanceOf(WorktreeNotPossibleException::class, $refusal);
	}
}
