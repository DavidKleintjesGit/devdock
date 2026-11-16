<?php

declare(strict_types=1);

namespace Tests\Unit\Review\Policies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Review\Enums\TestCommandEnum;
use Src\Review\Objects\TestCommandFactsObject;
use Src\Review\Policies\TestCommandPolicy;

/**
 * @see TestCommandPolicy
 */
class TestCommandPolicyTest extends TestCase
{
	#[Test]
	public function decide_runs_composer_test_when_the_project_defines_it(): void
	{
		$testCommand = (new TestCommandPolicy())->decide(new TestCommandFactsObject(lensCommand: 'php artisan test', hasComposerTestScript: true));

		$this->assertSame(TestCommandEnum::COMPOSER_TEST, $testCommand);
	}

	#[Test]
	public function decide_keeps_the_plain_test_command_without_a_composer_script(): void
	{
		$testCommand = (new TestCommandPolicy())->decide(new TestCommandFactsObject(lensCommand: 'php artisan test', hasComposerTestScript: false));

		$this->assertSame(TestCommandEnum::AS_SET, $testCommand);
	}

	#[Test]
	public function decide_keeps_a_command_set_by_hand(): void
	{
		$testCommand = (new TestCommandPolicy())->decide(new TestCommandFactsObject(lensCommand: 'php artisan test --filter=Energy', hasComposerTestScript: true));

		$this->assertSame(TestCommandEnum::AS_SET, $testCommand);
	}
}
