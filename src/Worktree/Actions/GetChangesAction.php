<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\ChangeObjectCollection;

/**
 * What a round left behind in the folder it ran in.
 *
 * @method static ChangeObjectCollection execute(string $directory)
 */
class GetChangesAction extends Command
{
	public function __construct(
		public readonly string $directory,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): ChangeObjectCollection
	{
		return $worktreeRepository->getChanges($this->directory);
	}
}
