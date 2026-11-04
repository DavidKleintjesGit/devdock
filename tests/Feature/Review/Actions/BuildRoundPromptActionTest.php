<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\BuildRoundPromptAction;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewFinding;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\ReviewRoundId;
use Tests\TestCase;

/**
 * @see BuildRoundPromptAction
 */
class BuildRoundPromptActionTest extends TestCase
{
	#[Test]
	public function execute_hands_the_agent_the_open_findings_and_not_the_ignored_ones(): void
	{
		$reviewRound = ReviewRound::factory()->create(['status' => RoundStatusEnum::FAILED->value]);
		$openFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'status' => FindingStatusEnum::OPEN->value, 'first_review_round_id' => $reviewRound->id]);
		$ignoredFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'lens_id' => $openFinding->lens_id, 'status' => FindingStatusEnum::IGNORED->value, 'first_review_round_id' => $reviewRound->id]);
		ReviewResult::factory()->create(['review_round_id' => $reviewRound->id, 'lens_id' => $openFinding->lens_id, 'verdict' => VerdictEnum::FAIL->value, 'findings' => 1]);
		$reviewRoundEntity = resolve(ReviewRoundRepositoryContract::class)->getById(new ReviewRoundId(id: $reviewRound->id));

		$prompt = (string) BuildRoundPromptAction::execute($reviewRoundEntity);

		$this->assertStringContainsString('#' . $openFinding->number . ' ', $prompt);
		$this->assertStringNotContainsString($ignoredFinding->text, $prompt);
	}
}
