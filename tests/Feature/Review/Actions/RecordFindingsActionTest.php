<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\RecordFindingsAction;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Models\Lens;
use Src\Review\Models\ReviewFinding;
use Src\Review\Models\ReviewRound;
use Src\Review\Objects\LensId;
use Src\Review\Objects\NewFindingObject;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\VerdictObject;
use Tests\TestCase;

/**
 * @see RecordFindingsAction
 */
class RecordFindingsActionTest extends TestCase
{
	#[Test]
	public function execute_gives_a_new_finding_the_next_number_of_the_card(): void
	{
		$lens = Lens::factory()->create();
		$reviewRound = ReviewRound::factory()->create();
		ReviewFinding::factory()->create(['card' => $reviewRound->card, 'number' => 4]);

		RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $lens->id), new VerdictObject(
			verdict: VerdictEnum::FAIL,
			findings: 1,
			report: '',
			newFindings: [new NewFindingObject(location: 'app/Order.php:42', text: fake()->sentence())],
		));

		$this->assertDatabaseHas('review_findings', [
			'card' => $reviewRound->card,
			'lens_id' => $lens->id,
			'number' => 5,
			'location' => 'app/Order.php:42',
		]);
	}

	#[Test]
	public function execute_resolves_an_earlier_finding_the_lens_saw_fixed(): void
	{
		$reviewRound = ReviewRound::factory()->create();
		$reviewFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'status' => FindingStatusEnum::OPEN->value]);

		RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $reviewFinding->lens_id), new VerdictObject(
			verdict: VerdictEnum::PASS,
			findings: 0,
			report: '',
			earlierFindings: [$reviewFinding->number => FindingStatusEnum::RESOLVED],
		));

		$this->assertDatabaseHas('review_findings', [
			'id' => $reviewFinding->id,
			'status' => FindingStatusEnum::RESOLVED->value,
			'resolved_review_round_id' => $reviewRound->id,
		]);
	}

	#[Test]
	public function execute_resolves_the_open_findings_of_a_lens_that_passes_without_naming_them(): void
	{
		$reviewRound = ReviewRound::factory()->create();
		$reviewFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'status' => FindingStatusEnum::OPEN->value]);

		RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $reviewFinding->lens_id), new VerdictObject(
			verdict: VerdictEnum::PASS,
			findings: 0,
			report: 'PASS',
		));

		$this->assertDatabaseHas('review_findings', [
			'id' => $reviewFinding->id,
			'status' => FindingStatusEnum::RESOLVED->value,
			'resolved_review_round_id' => $reviewRound->id,
		]);
	}

	#[Test]
	public function execute_keeps_open_what_a_passing_lens_still_calls_open(): void
	{
		$reviewRound = ReviewRound::factory()->create();
		$reviewFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'status' => FindingStatusEnum::OPEN->value]);

		RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $reviewFinding->lens_id), new VerdictObject(
			verdict: VerdictEnum::PASS,
			findings: 1,
			report: '',
			earlierFindings: [$reviewFinding->number => FindingStatusEnum::OPEN],
		));

		$this->assertDatabaseHas('review_findings', [
			'id' => $reviewFinding->id,
			'status' => FindingStatusEnum::OPEN->value,
		]);
	}

	#[Test]
	public function execute_keeps_a_finding_you_ignored_ignored_whatever_the_lens_says(): void
	{
		$reviewRound = ReviewRound::factory()->create();
		$reviewFinding = ReviewFinding::factory()->create(['card' => $reviewRound->card, 'status' => FindingStatusEnum::IGNORED->value, 'reason' => fake()->sentence()]);

		RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $reviewFinding->lens_id), new VerdictObject(
			verdict: VerdictEnum::FAIL,
			findings: 1,
			report: '',
			earlierFindings: [$reviewFinding->number => FindingStatusEnum::OPEN],
		));

		$this->assertDatabaseHas('review_findings', [
			'id' => $reviewFinding->id,
			'status' => FindingStatusEnum::IGNORED->value,
		]);
	}

	#[Test]
	public function execute_returns_how_many_findings_of_the_lens_are_still_open(): void
	{
		$reviewRound = ReviewRound::factory()->create();
		$lens = Lens::factory()->create();
		ReviewFinding::factory()->create(['card' => $reviewRound->card, 'lens_id' => $lens->id, 'status' => FindingStatusEnum::OPEN->value]);
		ReviewFinding::factory()->create(['card' => $reviewRound->card, 'lens_id' => $lens->id, 'status' => FindingStatusEnum::IGNORED->value]);

		$openFindings = RecordFindingsAction::execute($reviewRound->card, new ReviewRoundId(id: $reviewRound->id), new LensId(id: $lens->id), new VerdictObject(
			verdict: VerdictEnum::FAIL,
			findings: 1,
			report: '',
			newFindings: [new NewFindingObject(location: null, text: fake()->sentence())],
		));

		// one open from before + one new; the ignored one does not count
		$this->assertSame(2, $openFindings);
	}
}
