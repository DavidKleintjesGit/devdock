<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
	public function test_the_board_opens_for_someone_signed_in(): void
	{
		$response = $this->actingAs(User::factory()->create())->get('/');

		$response->assertOk();
	}

	public function test_the_board_sends_a_guest_to_the_login(): void
	{
		$response = $this->get('/');

		$response->assertRedirect(route('login'));
	}
}
