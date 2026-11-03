<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Src\Packages\Process\DetachedCommand;
use Src\Worktree\Actions\GetRepairStateAction;
use Src\Worktree\Actions\StartRepairAction;
use Src\Worktree\Enums\SetupCheckEnum;
use Tests\TestCase;

/**
 * @see StartRepairAction
 */
class StartRepairActionTest extends TestCase
{
	#[Test]
	public function execute_puts_the_repair_of_one_point_in_a_process_of_its_own(): void
	{
		$directory = sys_get_temp_dir() . '/' . fake()->uuid();
		$this->mock(DetachedCommand::class)
			->shouldReceive('artisan')
			->once()
			->with(Mockery::on(fn (string $command): bool => str_starts_with($command, 'devdock:repair') && str_contains($command, '--check=' . SetupCheckEnum::ASSETS->value)));

		StartRepairAction::execute($directory, SetupCheckEnum::ASSETS);
	}

	#[Test]
	public function execute_marks_the_repair_as_running_before_the_process_starts(): void
	{
		$directory = sys_get_temp_dir() . '/' . fake()->uuid();
		$this->mock(DetachedCommand::class)->shouldReceive('artisan');

		StartRepairAction::execute($directory);

		$this->assertTrue(GetRepairStateAction::execute($directory)->isRunning);
	}

	#[Test]
	public function execute_refuses_a_second_repair_while_one_runs(): void
	{
		$directory = sys_get_temp_dir() . '/' . fake()->uuid();
		$this->mock(DetachedCommand::class)->shouldReceive('artisan')->once();
		StartRepairAction::execute($directory);

		$isStarted = StartRepairAction::execute($directory);

		$this->assertFalse($isStarted);
	}
}
