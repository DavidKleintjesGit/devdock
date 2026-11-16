<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Changes;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see Changes
 */
class ChangesTest extends TestCase
{
	#[Test]
	public function show_sends_a_guest_to_the_login(): void
	{
		$response = $this->get(route('changes', ['card' => 'CSM-' . fake()->numberBetween(1, 9999)]));

		$response->assertRedirect(route('login'));
	}

	#[Test]
	public function show_opens_for_a_logged_in_user(): void
	{
		$user = User::factory()->create();

		$response = $this->actingAs($user)->get(route('changes', ['card' => 'CSM-' . fake()->numberBetween(1, 9999)]));

		$response->assertOk();
	}

	#[Test]
	public function render_tells_a_card_without_working_copy_apart(): void
	{
		$component = Livewire::test(Changes::class, ['card' => 'CSM-' . fake()->numberBetween(1, 9999)]);

		$component->assertViewHas('hasWorktree', false);
	}

	#[Test]
	public function look_for_changes_reports_nothing_without_working_copy(): void
	{
		$component = Livewire::test(Changes::class, ['card' => 'CSM-' . fake()->numberBetween(1, 9999)]);

		$component->call('lookForChanges')->assertReturned(false);
	}
}
