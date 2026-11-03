<?php

declare(strict_types=1);

namespace Tests\Feature\Access\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Src\Access\Actions\JudgeShellCommandAction;
use Tests\TestCase;

/**
 * @see JudgeShellCommandAction
 */
class JudgeShellCommandActionTest extends TestCase
{
	/**
	 * @return array<string, array{string}>
	 */
	public static function readingCommands(): array
	{
		return [
			'status' => ['git status'],
			'log with options in front' => ['git -C ../shop --no-pager log --oneline -5'],
			'diff in a chain' => ['cd app && git diff origin/main...HEAD | head'],
			'listing branches' => ['git branch -a'],
			'current branch' => ['git branch --show-current'],
			'listing tags by pattern' => ['git tag -l "v1.*"'],
			'listing stashes' => ['git stash list'],
			'reading config' => ['git config --get user.email'],
			'remote addresses' => ['git remote -v'],
			'listing worktrees' => ['git worktree list'],
			'git.exe by path' => ['"C:/Program Files/Git/bin/git.exe" show HEAD'],
			'no git at all' => ['php artisan test --filter=Order'],
			'git in a search string' => ['grep -rn "git commit" docs'],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function writingCommands(): array
	{
		return [
			'commit' => ['git commit -m "Fix"'],
			'push' => ['git push origin feature/billing'],
			'rebase' => ['git rebase origin/main'],
			'add' => ['git add .'],
			'commit behind config options' => ['git -c user.name=x commit -am wip'],
			'commit after a chain' => ['php artisan test && git commit -am done'],
			'commit in a pipe' => ['echo y | git commit -F -'],
			'commit in a subshell' => ['echo $(git commit -am wip)'],
			'commit handed to another shell' => ['sh -c "git commit -am wip"'],
			'commit in powershell' => ['cd app; git commit -am wip'],
			'git.exe by path' => ['"C:/Program Files/Git/bin/git.exe" push'],
			'deleting a branch' => ['git branch -D feature/old'],
			'renaming a branch' => ['git branch -m feature/new'],
			'setting an upstream' => ['git branch --set-upstream-to=origin/main'],
			'making a tag' => ['git tag v1.2.0'],
			'stashing' => ['git stash'],
			'discarding a file' => ['git checkout -- app/Order.php'],
			'switching' => ['git switch main'],
			'resetting' => ['git reset --hard HEAD~1'],
			'restoring' => ['git restore app/Order.php'],
			'cleaning' => ['git clean -fd'],
			'fetching' => ['git fetch origin'],
			'pulling' => ['git pull'],
			'merging' => ['git merge main'],
			'cherry-picking' => ['git cherry-pick abc123'],
			'writing config' => ['git config user.email someone@example.com'],
			'adding a remote' => ['git remote add fork https://example.com/fork.git'],
			'adding a worktree' => ['git worktree add ../copy main'],
		];
	}

	#[Test]
	#[DataProvider('readingCommands')]
	public function execute_lets_a_command_that_only_reads_git_run(string $command): void
	{
		$refusal = JudgeShellCommandAction::execute($command);

		$this->assertNull($refusal);
	}

	#[Test]
	#[DataProvider('writingCommands')]
	public function execute_refuses_a_command_that_writes_to_git(string $command): void
	{
		$refusal = JudgeShellCommandAction::execute($command);

		$this->assertNotNull($refusal);
	}

	#[Test]
	public function execute_names_the_refused_git_command_in_the_reason(): void
	{
		$refusal = JudgeShellCommandAction::execute('git push origin main');

		$this->assertStringContainsString('git push', $refusal);
	}
}
