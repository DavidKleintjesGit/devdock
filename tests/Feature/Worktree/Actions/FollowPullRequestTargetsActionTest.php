<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\PullRequest\Models\PullRequest;
use Src\Worktree\Actions\FollowPullRequestTargetsAction;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see FollowPullRequestTargetsAction
 */
class FollowPullRequestTargetsActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		$this->repository->startBranch('feature/parent');
		$this->repository->git('push', '--quiet', 'origin', 'feature/parent');
		$this->repository->checkout('main');
		$this->project = Project::factory()->create(['path' => $this->repository->path, 'remote' => 'git@bitbucket.org:mtvtd/shop.git']);
		Worktree::factory()->create([
			'project' => $this->project->name,
			'branch' => 'feature/child',
			'path' => $this->repository->addWorktree('feature/child'),
		]);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_points_a_working_copy_at_the_branch_its_pull_request_merges_into(): void
	{
		PullRequest::factory()->create(['repository' => 'shop', 'source_branch' => 'feature/child', 'destination_branch' => 'feature/parent']);

		FollowPullRequestTargetsAction::execute();

		$this->assertSame('feature/parent', $this->repository->git('config', '--get', 'branch.feature/child.devdock-target'));
	}

	#[Test]
	public function execute_leaves_the_target_of_a_merged_pull_request_behind(): void
	{
		$this->repository->git('config', 'branch.feature/child.devdock-target', 'feature/parent');
		PullRequest::factory()->merged()->create(['repository' => 'shop', 'source_branch' => 'feature/child', 'destination_branch' => 'feature/parent']);

		FollowPullRequestTargetsAction::execute();

		$this->assertFalse($this->repository->gitSucceedsWith([], 'config', '--get', 'branch.feature/child.devdock-target'));
	}

	#[Test]
	public function execute_ignores_a_pull_request_of_another_repository_with_the_same_branch(): void
	{
		PullRequest::factory()->create(['repository' => 'portal', 'source_branch' => 'feature/child', 'destination_branch' => 'feature/parent']);

		FollowPullRequestTargetsAction::execute();

		$this->assertFalse($this->repository->gitSucceedsWith([], 'config', '--get', 'branch.feature/child.devdock-target'));
	}
}
