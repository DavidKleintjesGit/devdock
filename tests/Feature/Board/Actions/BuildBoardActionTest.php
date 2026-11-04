<?php

declare(strict_types=1);

namespace Tests\Feature\Board\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\PullRequest\Models\PullRequest;
use Tests\TestCase;

/**
 * @see BuildBoardAction
 */
class BuildBoardActionTest extends TestCase
{
	#[Test]
	public function execute_gives_a_variant_branch_of_a_ticket_a_card_of_its_own(): void
	{
		PullRequest::factory()->create(['title' => 'CSM-3396 medewerkerskaart', 'source_branch' => 'feature/CSM-3396', 'destination_branch' => 'feature/CSM-3330']);
		PullRequest::factory()->create(['title' => 'CSM-3396 medewerkerskaart redesign', 'source_branch' => 'feature/CSM-3396-medewerkerskaart', 'destination_branch' => 'feature/CSM-3396']);

		$cards = collect(BuildBoardAction::execute())->keyBy(fn (CardObject $cardObject): string => $cardObject->id);

		$this->assertSame('feature/CSM-3396', $cards->get('CSM-3396')?->branch);
		$this->assertSame('feature/CSM-3396-medewerkerskaart', $cards->get('CSM-3396-medewerkerskaart')?->branch);
		$this->assertSame('CSM-3396', $cards->get('CSM-3396-medewerkerskaart')?->ticketKey);
		$this->assertSame('medewerkerskaart', $cards->get('CSM-3396-medewerkerskaart')?->variant());
	}

	#[Test]
	public function execute_keeps_one_card_for_a_ticket_worked_on_in_its_own_branch(): void
	{
		PullRequest::factory()->create(['title' => 'CSM-3554 geen sale', 'source_branch' => 'feature/CSM-3554', 'destination_branch' => 'master']);

		$cards = collect(BuildBoardAction::execute())->filter(fn (CardObject $cardObject): bool => $cardObject->ticketKey === 'CSM-3554');

		$this->assertCount(1, $cards);
		$this->assertNull($cards->first()?->variant());
	}
}
