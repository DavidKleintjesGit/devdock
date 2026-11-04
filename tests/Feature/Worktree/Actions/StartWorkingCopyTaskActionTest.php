<?php

declare(strict_types=1);

namespace Tests\Feature\Worktree\Actions;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Src\Packages\Process\DetachedCommand;
use Src\Worktree\Actions\GetWorkingCopyTaskAction;
use Src\Worktree\Actions\RunWorkingCopyTaskAction;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;
use Tests\TestCase;

/**
 * @see StartWorkingCopyTaskAction
 */
class StartWorkingCopyTaskActionTest extends TestCase
{
	#[Test]
	public function execute_hands_the_task_to_a_process_of_its_own(): void
	{
		$workingCopyTaskObject = new WorkingCopyTaskObject(card: 'todo:' . fake()->numberBetween(1, 100), task: WorkingCopyTaskEnum::LINK, project: fake()->slug(2), branch: 'feature/billing');
		$this->mock(DetachedCommand::class)
			->shouldReceive('artisan')
			->once()
			->with(Mockery::on(fn (string $command): bool => $command === 'devdock:working-copy-task --task=' . $workingCopyTaskObject->toArgument()));

		StartWorkingCopyTaskAction::execute($workingCopyTaskObject);
	}

	#[Test]
	public function execute_refuses_a_second_task_while_one_runs_for_the_card(): void
	{
		$workingCopyTaskObject = new WorkingCopyTaskObject(card: 'todo:' . fake()->numberBetween(1, 100), task: WorkingCopyTaskEnum::LINK, project: fake()->slug(2), branch: 'feature/billing');
		$this->mock(DetachedCommand::class)->shouldReceive('artisan')->once();
		StartWorkingCopyTaskAction::execute($workingCopyTaskObject);

		$isStarted = StartWorkingCopyTaskAction::execute($workingCopyTaskObject);

		$this->assertFalse($isStarted);
	}

	#[Test]
	public function run_writes_down_why_linking_failed(): void
	{
		// a project that is not on disk cannot be linked
		$workingCopyTaskObject = new WorkingCopyTaskObject(card: 'todo:' . fake()->numberBetween(1, 100), task: WorkingCopyTaskEnum::LINK, project: fake()->slug(2), branch: 'feature/billing');

		RunWorkingCopyTaskAction::execute($workingCopyTaskObject);

		$workingCopyTaskStateObject = GetWorkingCopyTaskAction::execute($workingCopyTaskObject->card);
		$this->assertFalse($workingCopyTaskStateObject->isRunning);
		$this->assertNotNull($workingCopyTaskStateObject->failure);
	}
}
