<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\ReadVerdictAction;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Tests\TestCase;

/**
 * @see ReadVerdictAction
 */
class ReadVerdictActionTest extends TestCase
{
	#[Test]
	public function execute_reads_what_became_of_earlier_findings_by_number(): void
	{
		$verdictObject = ReadVerdictAction::execute("```verdict\nFAIL\n\nEerder gevonden:\n- #12 RESOLVED\n- #13 OPEN still no guard\n```");

		$this->assertSame([12 => FindingStatusEnum::RESOLVED, 13 => FindingStatusEnum::OPEN], $verdictObject->earlierFindings);
	}

	#[Test]
	public function execute_reads_a_new_finding_with_its_place_and_what_is_wrong(): void
	{
		$verdictObject = ReadVerdictAction::execute("```verdict\nFAIL\n\n1. app/Order.php:42\n   The total skips the discount.\n```");

		$this->assertSame('app/Order.php:42', $verdictObject->newFindings[0]->location);
		$this->assertSame('The total skips the discount.', $verdictObject->newFindings[0]->text);
	}

	#[Test]
	public function execute_counts_open_earlier_findings_and_new_ones(): void
	{
		$verdictObject = ReadVerdictAction::execute("```verdict\nFAIL\n\n- #12 RESOLVED\n- #13 OPEN\n\nNieuw:\n1. app/Order.php:42\n   Wrong total.\n```");

		// #13 still open + one new finding; #12 is resolved
		$this->assertSame(2, $verdictObject->findings);
	}

	#[Test]
	public function execute_passes_when_every_earlier_finding_is_resolved(): void
	{
		$verdictObject = ReadVerdictAction::execute("```verdict\nPASS\n\n- #12 RESOLVED\n```");

		$this->assertSame(VerdictEnum::PASS, $verdictObject->verdict);
	}

	#[Test]
	public function execute_marks_an_answer_it_cannot_read(): void
	{
		$verdictObject = ReadVerdictAction::execute(fake()->sentence());

		$this->assertFalse($verdictObject->isReadable);
	}
}
