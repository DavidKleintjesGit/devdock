<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\SetFindingStatusAction;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\ReviewFinding;
use Src\Review\Models\ReviewResult;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\ReviewFindingId;
use Tests\TestCase;

/**
 * @see SetFindingStatusAction
 */
class SetFindingStatusActionTest extends TestCase
{
	#[Test]
	public function execute_sets_a_finding_aside_with_its_reason(): void
	{
		$reviewFinding = ReviewFinding::factory()->create(['status' => FindingStatusEnum::OPEN->value]);
		$reason = fake()->sentence();

		SetFindingStatusAction::execute(new ReviewFindingId(id: $reviewFinding->id), FindingStatusEnum::IGNORED, $reason);

		$this->assertDatabaseHas('review_findings', [
			'id' => $reviewFinding->id,
			'status' => FindingStatusEnum::IGNORED->value,
			'reason' => $reason,
		]);
	}

	#[Test]
	public function execute_turns_a_round_clean_once_its_last_open_finding_is_set_aside(): void
	{
		$reviewRound = ReviewRound::factory()->create(['status' => RoundStatusEnum::FAILED->value]);
		$reviewFinding = ReviewFinding::factory()->create([
			'card' => $reviewRound->card,
			'status' => FindingStatusEnum::OPEN->value,
			'first_review_round_id' => $reviewRound->id,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'lens_id' => $reviewFinding->lens_id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 1,
		]);

		SetFindingStatusAction::execute(new ReviewFindingId(id: $reviewFinding->id), FindingStatusEnum::IGNORED, fake()->sentence());

		$this->assertDatabaseHas('review_rounds', [
			'id' => $reviewRound->id,
			'status' => RoundStatusEnum::PASSED->value,
		]);
	}

	#[Test]
	public function execute_fails_the_round_again_when_a_finding_is_opened_again(): void
	{
		$reviewRound = ReviewRound::factory()->create(['status' => RoundStatusEnum::PASSED->value]);
		$reviewFinding = ReviewFinding::factory()->create([
			'card' => $reviewRound->card,
			'status' => FindingStatusEnum::IGNORED->value,
			'first_review_round_id' => $reviewRound->id,
		]);
		ReviewResult::factory()->create([
			'review_round_id' => $reviewRound->id,
			'lens_id' => $reviewFinding->lens_id,
			'verdict' => VerdictEnum::PASS->value,
			'findings' => 0,
		]);

		SetFindingStatusAction::execute(new ReviewFindingId(id: $reviewFinding->id), FindingStatusEnum::OPEN);

		$this->assertDatabaseHas('review_results', [
			'review_round_id' => $reviewRound->id,
			'verdict' => VerdictEnum::FAIL->value,
			'findings' => 1,
		]);
	}
}
