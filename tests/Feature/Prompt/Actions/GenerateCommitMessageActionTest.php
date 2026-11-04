<?php

declare(strict_types=1);

namespace Tests\Feature\Prompt\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Prompt\Actions\GenerateCommitMessageAction;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see GenerateCommitMessageAction
 */
class GenerateCommitMessageActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		$this->repository->commit('billing/Invoice.php', "<?php\n// total without discount\n", 'Add an invoice');
		$this->repository->write('billing/Invoice.php', "<?php\n// total with discount\n");
		$this->repository->write('billing/Draft.php', "<?php\n// not going along\n");
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_asks_about_the_changes_of_the_ticked_files_only_without_the_agent_session(): void
	{
		$question = null;
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('ask')
			->withArgs(function (mixed $roleAccessObject, string $directory, ?string $sessionUuid, string $asked) use (&$question): bool {
				$question = $asked;

				return $sessionUuid === null;
			})
			->andReturn("```commit\nApply the discount to the invoice total\n\n- Take the discount off the total\n```");

		$commitMessageObject = GenerateCommitMessageAction::execute($this->repository->path, ['billing/Invoice.php'], 'CSM-1');

		$this->assertStringContainsString('+// total with discount', (string) $question);
		$this->assertStringNotContainsString('not going along', (string) $question);
		$this->assertSame('CSM-1 Apply the discount to the invoice total', $commitMessageObject?->title);
	}

	#[Test]
	public function execute_asks_nothing_when_no_file_is_ticked(): void
	{
		$this->mock(ClaudeProcessRepositoryContract::class)->shouldNotReceive('ask');

		$commitMessageObject = GenerateCommitMessageAction::execute($this->repository->path, [], 'CSM-1');

		$this->assertNull($commitMessageObject);
	}
}
