<?php

declare(strict_types=1);

namespace Tests\Unit\Board\Policies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Board\Enums\CardSubtabEnum;
use Src\Board\Enums\CardTabEnum;
use Src\Board\Enums\HintToneEnum;
use Src\Board\Objects\TabHintFactsObject;
use Src\Board\Policies\TabHintPolicy;
use Src\Review\Enums\LoopStatusEnum;

/**
 * @see TabHintPolicy
 */
class TabHintPolicyTest extends TestCase
{
	/**
	 * A card with a working copy where nothing waits on you, with the facts of the test laid over it.
	 *
	 * @param  array<string, mixed>  $overrides
	 */
	private function facts(array $overrides = []): TabHintFactsObject
	{
		return new TabHintFactsObject(...array_merge([
			'hasUnreadQuestion' => false,
			'hasWorkingCopy' => true,
			'uncommittedFiles' => 0,
			'unpushedCommits' => 0,
			'isRebasing' => false,
			'hasConflict' => false,
			'behind' => 0,
			'isReady' => true,
			'loopStatus' => LoopStatusEnum::IDLE,
			'openFindings' => 0,
			'hasChangesRequested' => false,
			'unreadRemarks' => 0,
		], $overrides));
	}

	#[Test]
	public function decide_shows_no_dot_on_a_card_where_nothing_waits(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts());

		$this->assertSame([], array_merge(...array_map(fn (CardTabEnum $cardTab): array => $tabHints->forTab($cardTab), CardTabEnum::cases())));
	}

	#[Test]
	public function decide_marks_changes_amber_for_uncommitted_work(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['uncommittedFiles' => 3]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forSubtab(CardSubtabEnum::CHANGES));
	}

	#[Test]
	public function decide_marks_changes_amber_for_unpushed_commits(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['unpushedCommits' => 2]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forSubtab(CardSubtabEnum::CHANGES));
	}

	#[Test]
	public function decide_leaves_branch_quiet_for_unpushed_commits(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['unpushedCommits' => 2]));

		$this->assertSame([], $tabHints->forSubtab(CardSubtabEnum::BRANCH));
	}

	#[Test]
	public function decide_marks_branch_red_for_an_open_rebase(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['isRebasing' => true]));

		$this->assertSame([HintToneEnum::RED], $tabHints->forSubtab(CardSubtabEnum::BRANCH));
	}

	#[Test]
	public function decide_marks_branch_red_for_a_conflict(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['hasConflict' => true]));

		$this->assertSame([HintToneEnum::RED], $tabHints->forSubtab(CardSubtabEnum::BRANCH));
	}

	#[Test]
	public function decide_marks_branch_amber_for_running_behind(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['behind' => 4]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forSubtab(CardSubtabEnum::BRANCH));
	}

	#[Test]
	public function decide_marks_setup_red_for_a_working_copy_that_is_not_ready(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['isReady' => false]));

		$this->assertSame([HintToneEnum::RED], $tabHints->forSubtab(CardSubtabEnum::SETUP));
	}

	#[Test]
	public function decide_leaves_setup_quiet_when_readiness_was_not_looked_at(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['isReady' => null]));

		$this->assertSame([], $tabHints->forSubtab(CardSubtabEnum::SETUP));
	}

	#[Test]
	public function decide_leaves_the_working_copy_quiet_without_one(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['hasWorkingCopy' => false, 'isRebasing' => true, 'uncommittedFiles' => 3]));

		$this->assertSame([], $tabHints->forTab(CardTabEnum::WORKING_COPY));
	}

	#[Test]
	public function decide_gathers_the_working_copy_tab_red_before_amber(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['uncommittedFiles' => 3, 'hasConflict' => true]));

		$this->assertSame([HintToneEnum::RED, HintToneEnum::AMBER], $tabHints->forTab(CardTabEnum::WORKING_COPY));
	}

	#[Test]
	public function decide_marks_review_red_when_a_lens_fell_over(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['loopStatus' => LoopStatusEnum::LENS_FELL]));

		$this->assertSame([HintToneEnum::RED], $tabHints->forTab(CardTabEnum::REVIEW));
	}

	#[Test]
	public function decide_marks_review_amber_when_the_loop_hands_it_back(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['loopStatus' => LoopStatusEnum::STOPPED]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forTab(CardTabEnum::REVIEW));
	}

	#[Test]
	public function decide_marks_review_amber_for_open_findings_nobody_works_on(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['loopStatus' => LoopStatusEnum::IDLE, 'openFindings' => 2]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forTab(CardTabEnum::REVIEW));
	}

	#[Test]
	public function decide_leaves_review_quiet_while_the_agent_works_on_findings(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['loopStatus' => LoopStatusEnum::AGENT, 'openFindings' => 2]));

		$this->assertSame([], $tabHints->forTab(CardTabEnum::REVIEW));
	}

	#[Test]
	public function decide_marks_chat_amber_for_an_unread_question(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['hasUnreadQuestion' => true]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forTab(CardTabEnum::CHAT));
	}

	#[Test]
	public function decide_marks_connections_red_for_changes_asked(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['hasChangesRequested' => true]));

		$this->assertSame([HintToneEnum::RED], $tabHints->forTab(CardTabEnum::LINKS));
	}

	#[Test]
	public function decide_marks_connections_amber_for_unread_remarks(): void
	{
		$tabHints = (new TabHintPolicy())->decide($this->facts(['unreadRemarks' => 1]));

		$this->assertSame([HintToneEnum::AMBER], $tabHints->forTab(CardTabEnum::LINKS));
	}
}
