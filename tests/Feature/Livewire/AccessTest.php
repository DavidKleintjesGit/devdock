<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Access;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Src\Access\Enums\AgentRoleEnum;
use Src\Access\Objects\AccessOverviewObject;
use Src\Access\Objects\RoleAccessObject;
use Tests\TestCase;

/**
 * @see Access
 */
class AccessTest extends TestCase
{
	#[Test]
	public function render_shows_the_policy_of_every_role(): void
	{
		$component = Livewire::test(Access::class);

		$component->assertViewHas(
			'accessOverview',
			fn (AccessOverviewObject $accessOverviewObject): bool => array_map(
				fn (RoleAccessObject $roleAccessObject): AgentRoleEnum => $roleAccessObject->agentRole,
				$accessOverviewObject->roleAccesses,
			) === AgentRoleEnum::cases(),
		);
	}

	#[Test]
	public function render_shows_the_servers_whose_writing_tools_are_denied(): void
	{
		$server = fake()->slug(2);
		config(['devdock.access.servers' => [$server]]);

		$component = Livewire::test(Access::class);

		$component->assertSee($server);
	}

	#[Test]
	public function render_shows_the_operation_an_agent_may_still_write_through(): void
	{
		$operation = fake()->word() . 'Operation';
		config(['devdock.access.agent_operations' => [$operation]]);

		$component = Livewire::test(Access::class);

		$component->assertSee($operation);
	}

	#[Test]
	public function access_route_shows_the_page(): void
	{
		$response = $this->get(route('access'));

		$response->assertOk();
	}
}
