<?php

declare(strict_types=1);

namespace Tests\Feature\Prompt\Repositories;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Src\Access\Actions\GetRoleAccessAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Repositories\ClaudeProcessRepository;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see ClaudeProcessRepository
 */
class ClaudeProcessRepositoryTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		Process::fake();
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function ask_denies_a_helper_the_tools_that_change_files(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->ask(GetRoleAccessAction::execute(AgentRoleEnum::HELPER), $this->repository->path, null, fake()->sentence());

		Process::assertRan(fn (PendingProcess $pendingProcess): bool => in_array('Edit', explode(',', $this->valueAfter($pendingProcess, '--disallowedTools')), true));
	}

	#[Test]
	public function ask_puts_the_git_guard_in_front_of_every_shell_command(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->ask(GetRoleAccessAction::execute(AgentRoleEnum::HELPER), $this->repository->path, null, fake()->sentence());

		Process::assertRan(function (PendingProcess $pendingProcess): bool {
			$settings = json_decode((string) file_get_contents($this->valueAfter($pendingProcess, '--settings')), true);
			$shellGuard = collect($settings['hooks']['PreToolUse'])->firstWhere('matcher', 'Bash|PowerShell');

			return str_ends_with($shellGuard['hooks'][0]['command'], 'devdock:guard-git');
		});
	}

	#[Test]
	public function ask_starts_claude_where_a_commit_to_the_repository_is_refused(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->ask(GetRoleAccessAction::execute(AgentRoleEnum::HELPER), $this->repository->path, null, fake()->sentence());
		$this->repository->write('billing/Invoice.php', '<?php');
		$this->repository->git('add', 'billing/Invoice.php');

		$isCommitted = $this->repository->gitSucceedsWith($this->environmentOfClaude(), 'commit', '--no-verify', '-m', 'Add an invoice');

		$this->assertFalse($isCommitted);
	}

	#[Test]
	public function ask_starts_claude_where_a_push_is_refused(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->ask(GetRoleAccessAction::execute(AgentRoleEnum::HELPER), $this->repository->path, null, fake()->sentence());
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$isPushed = $this->repository->gitSucceedsWith($this->environmentOfClaude(), 'push', '--no-verify', 'origin', 'main');

		$this->assertFalse($isPushed);
	}

	#[Test]
	public function ask_leaves_a_commit_to_another_repository_alone(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->ask(GetRoleAccessAction::execute(AgentRoleEnum::HELPER), $this->repository->path, null, fake()->sentence());
		$otherRepository = TemporaryGitRepository::create();
		$otherRepository->write('billing/Invoice.php', '<?php');
		$otherRepository->git('add', 'billing/Invoice.php');

		$isCommitted = $otherRepository->gitSucceedsWith($this->environmentOfClaude(), 'commit', '-m', 'Add an invoice');

		$otherRepository->remove();
		$this->assertTrue($isCommitted);
	}

	#[Test]
	public function watch_leaves_an_agent_the_tools_that_change_files(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->watch(GetRoleAccessAction::execute(AgentRoleEnum::AGENT), $this->repository->path, fake()->sentence(), null, fn (array $event): null => null);

		Process::assertRan(fn (PendingProcess $pendingProcess): bool => ! in_array('Edit', explode(',', $this->valueAfter($pendingProcess, '--disallowedTools')), true));
	}

	#[Test]
	public function watch_denies_a_lens_the_tools_that_change_files(): void
	{
		resolve(ClaudeProcessRepositoryContract::class)->watch(GetRoleAccessAction::execute(AgentRoleEnum::LENS), $this->repository->path, fake()->sentence(), null, fn (array $event): null => null);

		Process::assertRan(fn (PendingProcess $pendingProcess): bool => in_array('Edit', explode(',', $this->valueAfter($pendingProcess, '--disallowedTools')), true));
	}

	private function valueAfter(PendingProcess $pendingProcess, string $option): string
	{
		$command = (array) $pendingProcess->command;

		return (string) ($command[array_search($option, $command, true) + 1] ?? '');
	}

	/**
	 * @return array<string, string>
	 */
	private function environmentOfClaude(): array
	{
		$environment = [];

		Process::assertRan(function (PendingProcess $pendingProcess) use (&$environment): bool {
			$environment = array_filter($pendingProcess->environment, fn (mixed $value): bool => is_string($value));

			return true;
		});

		return $environment;
	}
}
