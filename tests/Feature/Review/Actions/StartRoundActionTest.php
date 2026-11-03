<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Src\Board\Models\Todo;
use Src\Project\Models\Project;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun;
use Src\Review\Actions\RunLensAction;
use Src\Review\Actions\StartRoundAction;
use Src\Review\Contracts\LensProcessRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Exceptions\RoundNotStartedException;
use Src\Review\Models\Lens;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Models\Worktree;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see StartRoundAction
 */
class StartRoundActionTest extends TestCase
{
	private TemporaryGitRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = TemporaryGitRepository::create();
		$this->spy(LensProcessRepositoryContract::class);
		Bus::fake([RunLensAction::class]);
		config(['devdock.review.max_rounds' => 3]);

		// a working copy of DevDock that passes its checklist; there is no Herd to give it a site
		config(['devdock.herd.sites' => $this->repository->root . '/no-herd']);
		$this->repository->startBranch('feature/billing');
		$project = Project::factory()->create(['path' => $this->repository->path]);
		Worktree::factory()->create([
			'project' => $project->name,
			'branch' => 'feature/billing',
			'path' => $this->repository->path,
		]);
	}

	protected function tearDown(): void
	{
		$this->repository->remove();

		parent::tearDown();
	}

	#[Test]
	public function execute_opens_a_waiting_result_for_a_lens_that_touches_the_changes(): void
	{
		$todo = Todo::factory()->create();
		$lens = Lens::factory()->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertDatabaseHas('review_results', [
			'lens_id' => $lens->id,
			'verdict' => VerdictEnum::WAITING->value,
			'touched_files' => 1,
		]);
	}

	#[Test]
	public function execute_batches_one_job_per_waiting_lens(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->count(2)->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		Bus::assertBatched(fn (PendingBatch $pendingBatch): bool => $pendingBatch->jobs->count() === 2);
	}

	#[Test]
	public function execute_starts_a_worker_per_waiting_lens(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->count(2)->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->app->make(LensProcessRepositoryContract::class)->shouldHaveReceived('startWorkers')->once()->with(2);
	}

	#[Test]
	public function execute_skips_a_lens_whose_paths_are_not_touched(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$lens = Lens::factory()->create(['paths' => ['docs/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertDatabaseHas('review_results', [
			'lens_id' => $lens->id,
			'verdict' => VerdictEnum::SKIPPED->value,
		]);
	}

	#[Test]
	public function execute_settles_the_round_at_once_when_every_lens_is_skipped(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['docs/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertSame(RoundStatusEnum::PASSED, $reviewRoundEntity->status);
		Bus::assertNothingBatched();
	}

	#[Test]
	public function execute_returns_the_running_round_instead_of_opening_another(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'status' => RoundStatusEnum::RUNNING->value,
			'finished_at' => null,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertSame($reviewRound->id, $reviewRoundEntity->reviewRoundId->id);
		$this->assertDatabaseCount('review_rounds', 1);
	}

	#[Test]
	public function execute_continues_the_series_after_a_round_with_open_findings(): void
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
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		// round 2 stays in the series that round 1 began
		$this->assertSame(2, $reviewRoundEntity->number);
		$this->assertSame(1, $reviewRoundEntity->seriesStart);
	}

	#[Test]
	public function execute_refuses_a_round_past_the_maximum_while_findings_are_open(): void
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
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$this->expectExceptionObject(RoundNotStartedException::maximumReached(3));

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);
	}

	#[Test]
	public function execute_begins_a_new_series_after_a_clean_round_at_the_maximum(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'number' => 3,
			'series_start' => 1,
			'status' => RoundStatusEnum::PASSED->value,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::PASS->value,
			'findings' => 0,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertSame(4, $reviewRoundEntity->seriesStart);
	}

	#[Test]
	public function execute_begins_a_new_series_after_a_broken_off_round(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$reviewRound = ReviewRound::factory()->create([
			'card' => 'todo:' . $todo->id,
			'number' => 3,
			'series_start' => 1,
			'status' => RoundStatusEnum::STOPPED->value,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 2,
		]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);

		$this->assertSame(4, $reviewRoundEntity->seriesStart);
	}

	#[Test]
	public function execute_begins_a_new_series_when_the_loop_is_restarted(): void
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
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . $todo->id, $this->repository->path, true);

		$this->assertSame(4, $reviewRoundEntity->seriesStart);
	}

	#[Test]
	public function execute_refuses_a_round_when_nothing_is_changed(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);

		$this->expectExceptionObject(RoundNotStartedException::nothingChanged());

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);
	}

	#[Test]
	public function execute_returns_nothing_for_a_card_that_is_not_on_the_board(): void
	{
		Lens::factory()->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');

		$reviewRoundEntity = StartRoundAction::execute('todo:' . fake()->numberBetween(1000, 2000), $this->repository->path);

		$this->assertNull($reviewRoundEntity);
		$this->assertDatabaseCount('review_rounds', 0);
	}

	#[Test]
	public function execute_refuses_a_round_in_a_copy_that_is_not_ready(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		// the copy stands on another branch than the one of its working copy
		$this->repository->startBranch('feature/other');

		$this->expectException(SetupIncompleteException::class);

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);
	}

	#[Test]
	public function execute_refuses_a_round_while_the_agent_works_in_the_copy(): void
	{
		$todo = Todo::factory()->create();
		Lens::factory()->create(['paths' => ['billing/*']]);
		$this->repository->commit('billing/Invoice.php', '<?php', 'Add an invoice');
		PromptRun::factory()->create([
			'directory' => $this->repository->path,
			'status' => PromptStatusEnum::RUNNING->value,
			'started_at' => now(),
		]);

		$this->expectException(WorkingCopyBusyException::class);

		StartRoundAction::execute('todo:' . $todo->id, $this->repository->path);
	}
}
