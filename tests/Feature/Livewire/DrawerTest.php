<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Drawer;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Src\Board\Models\Todo;
use Src\Review\Enums\FindingStatusEnum;
use Src\Review\Models\ReviewFinding;
use Tests\TestCase;

/**
 * @see Drawer
 */
class DrawerTest extends TestCase
{
	/**
	 * @return array<string, array{string}>
	 */
	public static function tabs(): array
	{
		return [
			'overview' => ['Overzicht'],
			'agent' => ['Agent'],
			'working copy' => ['Werkkopie'],
			'review' => ['Review'],
		];
	}

	#[Test]
	#[DataProvider('tabs')]
	public function render_shows_the_title_of_the_card_on_every_tab(string $tab): void
	{
		$todo = Todo::factory()->create();

		$component = Livewire::withQueryParams(['card' => 'todo:' . $todo->id, 'tab' => $tab])->test(Drawer::class);

		$component->assertSee($todo->title);
	}

	#[Test]
	public function start_round_without_a_working_copy_names_that_as_the_failure(): void
	{
		$todo = Todo::factory()->create();
		$component = Livewire::withQueryParams(['card' => 'todo:' . $todo->id, 'tab' => 'Review'])->test(Drawer::class);

		$component->call('startRound');

		$component->assertSet('failure', __('Zonder werkkopie is er niets om de lenzen over te laten gaan.'));
	}

	#[Test]
	public function ignore_finding_without_a_reason_leaves_the_finding_open(): void
	{
		$reviewFinding = ReviewFinding::factory()->create(['status' => FindingStatusEnum::OPEN->value]);
		$component = Livewire::test(Drawer::class);

		$component->call('startIgnoringFinding', $reviewFinding->id)->call('ignoreFinding');

		$component->assertHasErrors(['ignoreReason' => 'required']);
		$this->assertDatabaseHas('review_findings', ['id' => $reviewFinding->id, 'status' => FindingStatusEnum::OPEN->value]);
	}

	#[Test]
	public function ignore_finding_sets_the_finding_aside_with_your_reason(): void
	{
		$reviewFinding = ReviewFinding::factory()->create(['status' => FindingStatusEnum::OPEN->value]);
		$reason = fake()->sentence();
		$component = Livewire::test(Drawer::class);

		$component->call('startIgnoringFinding', $reviewFinding->id)->set('ignoreReason', $reason)->call('ignoreFinding');

		$component->assertSet('ignoringFinding', null);
		$this->assertDatabaseHas('review_findings', ['id' => $reviewFinding->id, 'status' => FindingStatusEnum::IGNORED->value, 'reason' => $reason]);
	}

	#[Test]
	public function resolve_finding_marks_it_resolved_by_you(): void
	{
		$reviewFinding = ReviewFinding::factory()->create(['status' => FindingStatusEnum::OPEN->value]);

		Livewire::test(Drawer::class)->call('resolveFinding', $reviewFinding->id);

		$this->assertDatabaseHas('review_findings', ['id' => $reviewFinding->id, 'status' => FindingStatusEnum::RESOLVED->value, 'resolved_review_round_id' => null]);
	}

	#[Test]
	public function reopen_finding_puts_an_ignored_finding_back_without_its_reason(): void
	{
		$reviewFinding = ReviewFinding::factory()->create(['status' => FindingStatusEnum::IGNORED->value, 'reason' => fake()->sentence()]);

		Livewire::test(Drawer::class)->call('reopenFinding', $reviewFinding->id);

		$this->assertDatabaseHas('review_findings', ['id' => $reviewFinding->id, 'status' => FindingStatusEnum::OPEN->value, 'reason' => null]);
	}
}
