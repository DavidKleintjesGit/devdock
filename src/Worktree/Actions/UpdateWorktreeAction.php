<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Brings the branch up to date with the main line by merging it in. Returns
 * why that did not work, or null when it did.
 *
 * @method static ?string execute(string $directory)
 */
class UpdateWorktreeAction extends Command
{
	public function __construct(
		public readonly string $directory,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): ?string
	{
		return $worktreeRepository->update($this->directory);
	}
}
