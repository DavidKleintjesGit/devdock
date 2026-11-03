<?php

declare(strict_types=1);

namespace Tests\Feature\Board\Actions;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Src\Board\Actions\GetBoardAction;
use Src\Board\Models\Todo;
use Src\Board\Objects\CardObject;
use Src\Board\Objects\CardObjectCollection;
use stdClass;
use Tests\TestCase;

/**
 * @see GetBoardAction
 */
class GetBoardActionTest extends TestCase
{
	#[Test]
	public function execute_returns_a_card_for_a_todo(): void
	{
		$todo = Todo::factory()->create();

		$cardObjectCollection = GetBoardAction::execute();

		$this->assertContains('todo:' . $todo->id, collect($cardObjectCollection)->map(fn (CardObject $cardObject): string => $cardObject->id));
	}

	#[Test]
	public function execute_returns_the_kept_board_while_no_row_changed(): void
	{
		Todo::factory()->create();
		GetBoardAction::execute();
		Cache::put('board.cards', ['version' => Cache::get('board.cards')['version'], 'board' => serialize(CardObjectCollection::fromArray([]))]);

		$cardObjectCollection = GetBoardAction::execute();

		// the kept board was swapped for an empty one, so an empty board proves it was read back
		$this->assertCount(0, $cardObjectCollection);
	}

	#[Test]
	public function execute_builds_the_board_again_once_a_row_changed(): void
	{
		Todo::factory()->create();
		GetBoardAction::execute();
		Cache::put('board.cards', ['version' => Cache::get('board.cards')['version'], 'board' => serialize(CardObjectCollection::fromArray([]))]);
		$todo = Todo::factory()->create();

		$cardObjectCollection = GetBoardAction::execute();

		$this->assertContains('todo:' . $todo->id, collect($cardObjectCollection)->map(fn (CardObject $cardObject): string => $cardObject->id));
	}

	#[Test]
	public function execute_builds_the_board_again_when_the_kept_one_holds_a_class_from_outside_the_domain(): void
	{
		$todo = Todo::factory()->create();
		GetBoardAction::execute();
		Cache::put('board.cards', ['version' => Cache::get('board.cards')['version'], 'board' => serialize(new stdClass())]);

		$cardObjectCollection = GetBoardAction::execute();

		$this->assertContains('todo:' . $todo->id, collect($cardObjectCollection)->map(fn (CardObject $cardObject): string => $cardObject->id));
	}
}
