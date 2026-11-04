<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\FakeProcessResult;
use PHPUnit\Framework\Attributes\Test;
use Src\Project\Models\Project;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Review\Actions\RunLensAction;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundId;
use Src\Worktree\Models\Worktree;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process;
use Tests\Support\TemporaryGitRepository;
use Tests\TestCase;

/**
 * @see RunLensAction
 */
class RunLensActionTest extends TestCase
{
	#[Test]
	public function execute_writes_an_error_instead_of_judging_in_a_copy_that_is_not_ready(): void
	{
		$repository = TemporaryGitRepository::create();
		// the folder of the round is no working copy of DevDock
		$reviewRound = ReviewRound::factory()->create([
			'directory' => $repository->path,
			'status' => RoundStatusEnum::RUNNING->value,
			'finished_at' => null,
		]);
		$reviewResult = ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::WAITING->value,
			'finished_at' => null,
		]);
		$this->spy(ClaudeProcessRepositoryContract::class);

		RunLensAction::execute(new ReviewRoundId(id: $reviewRound->id), new LensId(id: $reviewResult->lens_id));

		$repository->remove();
		$this->assertDatabaseHas('review_results', [
			'id' => $reviewResult->id,
			'verdict' => VerdictEnum::ERROR->value,
		]);
		$this->app->make(ClaudeProcessRepositoryContract::class)->shouldNotHaveReceived('watch');
	}

	#[Test]
	public function execute_writes_why_a_lens_stopped_when_it_ran_out_of_time_without_a_verdict(): void
	{
		[$repository, $reviewResult] = $this->roundInReadyCopy();
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('watch')
			->andThrow($this->timedOut());

		RunLensAction::execute(new ReviewRoundId(id: $reviewResult->review_round_id), new LensId(id: $reviewResult->lens_id));

		$repository->remove();
		$this->assertDatabaseHas('review_results', [
			'id' => $reviewResult->id,
			'verdict' => VerdictEnum::ERROR->value,
			'report' => __('De lens gaf binnen de tijd geen oordeel en is gestopt.'),
		]);
	}

	#[Test]
	public function execute_keeps_the_verdict_a_lens_gave_before_it_ran_out_of_time(): void
	{
		[$repository, $reviewResult] = $this->roundInReadyCopy();
		$this->mock(ClaudeProcessRepositoryContract::class)
			->shouldReceive('watch')
			->andReturnUsing(function (mixed ...$arguments): never {
				// the lens answers, then waits on a test suite it left running in the background
				$arguments[4](['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => "```verdict\nPASS\n```"]]]]);

				throw $this->timedOut();
			});

		RunLensAction::execute(new ReviewRoundId(id: $reviewResult->review_round_id), new LensId(id: $reviewResult->lens_id));

		$repository->remove();
		$this->assertDatabaseHas('review_results', [
			'id' => $reviewResult->id,
			'verdict' => VerdictEnum::PASS->value,
		]);
	}

	/**
	 * A running round in a working copy of DevDock that passes its checklist, with one lens waiting.
	 *
	 * @return array{TemporaryGitRepository, ReviewResult}
	 */
	private function roundInReadyCopy(): array
	{
		$repository = TemporaryGitRepository::create();
		config(['devdock.herd.sites' => $repository->root . '/no-herd']);
		$repository->startBranch('feature/billing');
		$project = Project::factory()->create(['path' => $repository->path]);
		Worktree::factory()->create(['project' => $project->name, 'branch' => 'feature/billing', 'path' => $repository->path]);
		$reviewRound = ReviewRound::factory()->create([
			'directory' => $repository->path,
			'status' => RoundStatusEnum::RUNNING->value,
			'finished_at' => null,
		]);
		$reviewResult = ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::WAITING->value,
			'finished_at' => null,
		]);

		return [$repository, $reviewResult];
	}

	private function timedOut(): ProcessTimedOutException
	{
		return new ProcessTimedOutException(
			new SymfonyTimeoutException(new Process(['claude']), SymfonyTimeoutException::TYPE_IDLE),
			new FakeProcessResult(),
		);
	}
}
