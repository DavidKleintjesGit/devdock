<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Puts the commits of the branch on top of the main line. Returns why that
 * did not work, or null when it did.
 *
 * @method static ?string execute(string $directory)
 */
class RebaseWorktreeAction extends Command
{
	public function __construct(
		public readonly string $directory,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): ?string
	{
		return $worktreeRepository->rebase($this->directory);
	}
}
