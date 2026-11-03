<?php

declare(strict_types=1);

namespace Tests\Feature\Prompt\Actions;

use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Src\Board\Models\Todo;
use Src\Prompt\Actions\RunPromptAction;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun;
use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunId;
use Src\Review\Actions\RunLensAction;
use Src\Review\Contracts\LensProcessRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\Lens;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Objects\SessionObjectCollection;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see RunPromptAction
 */
class RunPromptActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		$this->spy(LensProcessRepositoryContract::class);
		// the sessions on this machine are not the test's to read or rename
		$this->mock(SessionFileRepositoryContract::class)->shouldReceive('getAll')->andReturn(SessionObjectCollection::fromArray([]));
		Bus::fake([RunLensAction::class]);
		config(['devdock.review.max_rounds' => 3]);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_marks_the_run_done_with_the_answer_of_claude(): void
	{
		$promptRun = PromptRun::factory()->create(['status' => PromptStatusEnum::RUNNING->value]);
		$answer = fake()->sentence();
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('run')
			->andReturn(new PromptResultObject(hasFailed: false, sessionUuid: null, output: $answer, message: null, cost: null));

		RunPromptAction::execute(new PromptRunId(id: $promptRun->id));

		$this->assertDatabaseHas('prompt_runs', [
			'id' => $promptRun->id,
			'status' => PromptStatusEnum::DONE->value,
			'output' => $answer,
		]);
	}

	#[Test]
	public function execute_marks_the_run_failed_when_claude_cannot_be_started(): void
	{
		$promptRun = PromptRun::factory()->create(['status' => PromptStatusEnum::RUNNING->value]);
		$message = fake()->sentence();
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('run')
			->andThrow(new RuntimeException($message));

		RunPromptAction::execute(new PromptRunId(id: $promptRun->id));

		$this->assertDatabaseHas('prompt_runs', [
			'id' => $promptRun->id,
			'status' => PromptStatusEnum::FAILED->value,
			'message' => $message,
		]);
	}

	#[Test]
	public function execute_sends_the_lenses_back_when_the_agent_reports_a_turn_on_a_round_done(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'number' => 1,
			'series_start' => 1,
			'status' => RoundStatusEnum::FAILED->value,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 2,
		]);
		$promptRun = PromptRun::factory()->create([
			'card' => 'todo:' . $todo->id,
			'review_round_id' => $reviewRound->id,
			'directory' => $this->repository->path,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('run')
			->andReturn(new PromptResultObject(hasFailed: false, sessionUuid: null, output: "```report\nstatus: done\nsummary: fixed\n```", message: null, cost: null));

		RunPromptAction::execute(new PromptRunId(id: $promptRun->id));

		$this->assertDatabaseHas('review_rounds', [
			'card' => 'todo:' . $todo->id,
			'number' => 2,
		]);
	}

	#[Test]
	public function execute_leaves_the_loop_waiting_when_the_agent_asks_a_question(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'number' => 1,
			'series_start' => 1,
			'status' => RoundStatusEnum::FAILED->value,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 2,
		]);
		$promptRun = PromptRun::factory()->create([
			'card' => 'todo:' . $todo->id,
			'review_round_id' => $reviewRound->id,
			'directory' => $this->repository->path,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('run')
			->andReturn(new PromptResultObject(hasFailed: false, sessionUuid: null, output: "```report\nstatus: question\nquestion: which one?\n```", message: null, cost: null));

		RunPromptAction::execute(new PromptRunId(id: $promptRun->id));

		$this->assertDatabaseCount('review_rounds', 1);
	}

	#[Test]
	public function execute_finishes_the_run_when_the_loop_is_at_its_maximum(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'number' => 3,
			'series_start' => 1,
			'status' => RoundStatusEnum::FAILED->value,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 2,
		]);
		$promptRun = PromptRun::factory()->create([
			'card' => 'todo:' . $todo->id,
			'review_round_id' => $reviewRound->id,
			'directory' => $this->repository->path,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('run')
			->andReturn(new PromptResultObject(hasFailed: false, sessionUuid: null, output: "```report\nstatus: done\nsummary: fixed\n```", message: null, cost: null));

		RunPromptAction::execute(new PromptRunId(id: $promptRun->id));

		// round 3 of 3 left findings open, so the loop waits for you instead of opening round 4
		$this->assertDatabaseCount('review_rounds', 1);
		$this->assertDatabaseHas('prompt_runs', [
			'id' => $promptRun->id,
			'status' => PromptStatusEnum::DONE->value,
		]);
	}
}
