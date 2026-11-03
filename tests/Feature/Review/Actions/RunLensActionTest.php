<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Review\Actions\RunLensAction;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\LensId;
use Src\Review\Objects\ReviewRoundId;
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
}
