<?php

declare(strict_types=1);

namespace Tests\Feature\Review\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Review\Actions\GetRoundChoicesAction;
use Src\Review\Actions\KeepRoundChoicesAction;
use Src\Review\Enums\FindingChoiceEnum;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\RoundChoicesObject;
use Tests\TestCase;

/**
 * @see KeepRoundChoicesAction
 */
class KeepRoundChoicesActionTest extends TestCase
{
	#[Test]
	public function execute_keeps_the_choices_and_your_own_message_for_the_round(): void
	{
		$reviewRoundId = new ReviewRoundId(id: fake()->numberBetween(1, 100000));
		$findingId = fake()->numberBetween(1, 100000);
		$roundChoicesObject = new RoundChoicesObject(
			choices: [$findingId => FindingChoiceEnum::IGNORE->value],
			reasons: [$findingId => fake()->sentence()],
			message: fake()->paragraph(),
		);

		KeepRoundChoicesAction::execute($reviewRoundId, $roundChoicesObject);

		$this->assertEquals($roundChoicesObject, GetRoundChoicesAction::execute($reviewRoundId));
	}

	#[Test]
	public function execute_forgets_the_choices_of_a_round_without_choices(): void
	{
		$reviewRoundId = new ReviewRoundId(id: fake()->numberBetween(1, 100000));
		KeepRoundChoicesAction::execute($reviewRoundId, new RoundChoicesObject(choices: [], reasons: []));

		KeepRoundChoicesAction::execute($reviewRoundId, null);

		$this->assertNull(GetRoundChoicesAction::execute($reviewRoundId));
	}
}
