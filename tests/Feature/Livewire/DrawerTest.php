<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Drawer;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Src\Board\Models\Todo;
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
}
