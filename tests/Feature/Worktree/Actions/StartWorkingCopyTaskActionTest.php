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
use Tests\Support\TemporaryGitRepository;
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
	public function execute_queues_a_second_task_while_one_runs_for_the_card(): void
	{
		$card = 'todo:' . fake()->numberBetween(1, 100);
		$workingCopyTaskObject1 = new WorkingCopyTaskObject(card: $card, task: WorkingCopyTaskEnum::LINK, project: fake()->slug(2), branch: 'feature/billing');
		$workingCopyTaskObject2 = new WorkingCopyTaskObject(card: $card, task: WorkingCopyTaskEnum::PUSH, project: $workingCopyTaskObject1->project, branch: 'feature/billing');
		// only the first task gets a process; the second waits in line behind it
		$this->mock(DetachedCommand::class)->shouldReceive('artisan')->once();
		StartWorkingCopyTaskAction::execute($workingCopyTaskObject1);

		StartWorkingCopyTaskAction::execute($workingCopyTaskObject2);

		$this->assertSame([$workingCopyTaskObject2->toArgument()], GetWorkingCopyTaskAction::execute($card)->queued);
	}

	#[Test]
	public function run_hands_over_to_the_next_task_in_line(): void
	{
		$card = 'todo:' . fake()->numberBetween(1, 100);
		// a project that is not on disk cannot be linked, so the first task ends at once
		$workingCopyTaskObject1 = new WorkingCopyTaskObject(card: $card, task: WorkingCopyTaskEnum::LINK, project: fake()->slug(2), branch: 'feature/billing');
		$workingCopyTaskObject2 = new WorkingCopyTaskObject(card: $card, task: WorkingCopyTaskEnum::PUSH, project: $workingCopyTaskObject1->project, branch: 'feature/billing');
		$this->mock(DetachedCommand::class)->shouldReceive('artisan')->once();
		StartWorkingCopyTaskAction::execute($workingCopyTaskObject1);
		StartWorkingCopyTaskAction::execute($workingCopyTaskObject2);

		$workingCopyTaskStateObject = RunWorkingCopyTaskAction::execute($workingCopyTaskObject1);

		$this->assertSame($workingCopyTaskObject2->toArgument(), $workingCopyTaskStateObject->next);
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

	#[Test]
	public function run_pushes_the_branch_and_leaves_the_notice_for_the_panel(): void
	{
		$repository = TemporaryGitRepository::create();
		$path = $repository->addWorktree('feature/billing');
		file_put_contents($path . '/Invoice.php', '<?php');
		$repository->git('-C', $path, 'add', '.');
		$repository->git('-C', $path, 'commit', '--quiet', '-m', 'Add an invoice');
		$workingCopyTaskObject = new WorkingCopyTaskObject(card: 'todo:' . fake()->numberBetween(1, 100), task: WorkingCopyTaskEnum::PUSH, project: fake()->slug(2), branch: 'feature/billing', path: $path);

		RunWorkingCopyTaskAction::execute(WorkingCopyTaskObject::fromArgument($workingCopyTaskObject->toArgument()));

		$workingCopyTaskStateObject = GetWorkingCopyTaskAction::execute($workingCopyTaskObject->card);
		$this->assertSame(__('Gepusht naar origin.'), $workingCopyTaskStateObject->notice);
		$this->assertNull($workingCopyTaskStateObject->failure);
		$this->assertSame($repository->git('-C', $path, 'rev-parse', 'HEAD'), $repository->git('rev-parse', 'origin/feature/billing'));

		$repository->remove();
	}
}
