<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * Refuses a folder that does not stand on the branch of the task, so an agent
 * or a lens never works on something else than the task. A rebase of that
 * branch standing open counts as standing on it when the work is to finish it.
 *
 * @method static void execute(string $directory, string $branch, bool $acceptsRebase = false)
 *
 * @throws WorktreeNotPossibleException
 */
class EnsureOnBranchAction extends Command
{
	public function __construct(
		public readonly string $directory,
		public readonly string $branch,
		public readonly bool $acceptsRebase = false,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): void
	{
		$standsOn = $worktreeRepository->getBranch($this->directory);

		if ($standsOn === $this->branch) {
			return;
		}

		// during a rebase HEAD stands on no branch; git notes which branch it rebases
		if ($this->acceptsRebase && $worktreeRepository->getRebasingBranch($this->directory) === $this->branch) {
			return;
		}

		throw new WorktreeNotPossibleException(__('De map :path staat op :actual, niet op :branch. Een agent of lens zou daar niet aan deze taak werken; verhuis de taak naar een eigen werkkopie.', [
			'path' => $this->directory,
			'actual' => $standsOn ?? __('geen branch'),
			'branch' => $this->branch,
		]));
	}
}
