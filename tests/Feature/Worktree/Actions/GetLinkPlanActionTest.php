<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Board\Models\Todo;
use Src\Worktree\Actions\GetLinkPlanAction;
use Src\Worktree\Enums\LinkStepEnum;
use Tests\TestCase;

/**
 * @see GetLinkPlanAction
 */
class GetLinkPlanActionTest extends TestCase
{
	#[Test]
	public function execute_asks_a_card_without_a_branch_to_choose_one_first(): void
	{
		$todo = Todo::factory()->create(['project' => fake()->slug(2)]);

		$linkPlans = GetLinkPlanAction::execute('todo:' . $todo->id);

		$this->assertSame(LinkStepEnum::NEEDS_BRANCH, $linkPlans[0]->linkStep);
	}

	#[Test]
	public function execute_leaves_out_a_card_without_a_project(): void
	{
		$todo = Todo::factory()->create(['project' => null]);

		$linkPlans = GetLinkPlanAction::execute('todo:' . $todo->id);

		$this->assertSame([], $linkPlans);
	}
}
