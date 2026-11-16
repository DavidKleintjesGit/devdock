<?php

declare(strict_types=1);

namespace Src\Review\Policies;

use Src\Review\Enums\TestCommandEnum;
use Src\Review\Objects\TestCommandFactsObject;

/**
 * The rule for the plain test command: a project that defines `composer test`
 * runs its suite the way its team does, in parallel when it is set up for it.
 * A command set by hand on a lens stays as it is.
 */
class TestCommandPolicy
{
	private const PLAIN_TEST_COMMAND = 'php artisan test';

	public function decide(TestCommandFactsObject $testCommandFactsObject): TestCommandEnum
	{
		return trim((string) $testCommandFactsObject->lensCommand) === self::PLAIN_TEST_COMMAND && $testCommandFactsObject->hasComposerTestScript
			? TestCommandEnum::COMPOSER_TEST
			: TestCommandEnum::AS_SET;
	}
}
