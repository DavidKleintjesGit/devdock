<?php

declare(strict_types=1);

namespace Tests\Unit\Review\Policies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensFindingsFactsObject;
use Src\Review\Policies\LensVerdictPolicy;

/**
 * @see LensVerdictPolicy
 */
class LensVerdictPolicyTest extends TestCase
{
	#[Test]
	public function decide_passes_a_lens_without_open_findings(): void
	{
		$verdict = (new LensVerdictPolicy())->decide(new LensFindingsFactsObject(isReadable: true, openFindings: 0));

		$this->assertSame(VerdictEnum::PASS, $verdict);
	}

	#[Test]
	public function decide_fails_a_lens_with_an_open_finding(): void
	{
		$verdict = (new LensVerdictPolicy())->decide(new LensFindingsFactsObject(isReadable: true, openFindings: 1));

		$this->assertSame(VerdictEnum::FAIL, $verdict);
	}

	#[Test]
	public function decide_fails_a_lens_whose_answer_could_not_be_read(): void
	{
		$verdict = (new LensVerdictPolicy())->decide(new LensFindingsFactsObject(isReadable: false, openFindings: 0));

		$this->assertSame(VerdictEnum::FAIL, $verdict);
	}
}
