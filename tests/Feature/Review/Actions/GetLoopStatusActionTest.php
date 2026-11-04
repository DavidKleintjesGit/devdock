<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Prompt\Enums\PromptStatusEnum;
use Src\Prompt\Models\PromptRun;
use Src\Review\Actions\GetLoopStatusAction;
use Src\Review\Actions\GetRoundTurnsAction;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\LoopStatusEnum;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\ReviewRoundId;
use Tests\TestCase;

/**
 * @see GetLoopStatusAction
 */
class GetLoopStatusActionTest extends TestCase
{
	#[Test]
	public function execute_does_not_call_a_round_clean_when_a_lens_fell_over(): void
	{
		$reviewRound = ReviewRound::factory()->create(['number' => 1, 'series_start' => 1, 'status' => RoundStatusEnum::FAILED->value]);
		ReviewResult::factory()->create(['review_round_id' => $reviewRound->id, 'verdict' => VerdictEnum::PASS->value, 'findings' => 0]);
		ReviewResult::factory()->create(['review_round_id' => $reviewRound->id, 'verdict' => VerdictEnum::ERROR->value, 'findings' => 0]);

		$loopStatus = GetLoopStatusAction::execute(resolve(ReviewRoundRepositoryContract::class)->getById(new ReviewRoundId(id: $reviewRound->id)), null, 3);

		$this->assertSame(LoopStatusEnum::LENS_FELL, $loopStatus);
	}

	#[Test]
	public function execute_says_the_next_round_did_not_start_after_the_agent_reported_done(): void
	{
		$reviewRound = ReviewRound::factory()->create(['number' => 1, 'series_start' => 1, 'status' => RoundStatusEnum::FAILED->value]);
		ReviewResult::factory()->create(['review_round_id' => $reviewRound->id, 'verdict' => VerdictEnum::FAIL->value, 'findings' => 2]);
		PromptRun::factory()->create([
			'card' => $reviewRound->card,
			'review_round_id' => $reviewRound->id,
			'status' => PromptStatusEnum::DONE->value,
			'output' => "```report\nstatus: done\nsummary: fixed\n```",
		]);
		$roundTurns = GetRoundTurnsAction::execute($reviewRound->card);

		$loopStatus = GetLoopStatusAction::execute(resolve(ReviewRoundRepositoryContract::class)->getById(new ReviewRoundId(id: $reviewRound->id)), $roundTurns[$reviewRound->id] ?? null, 3);

		$this->assertSame(LoopStatusEnum::NEXT_ROUND, $loopStatus);
		$this->assertTrue($loopStatus->needsYou());
	}
}
