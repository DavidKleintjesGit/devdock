<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Sets where a push of this branch goes, or lets the first push decide.
 *
 * @method static bool execute(string $directory, ?string $upstream)
 */
class SetUpstreamAction extends Command
{
	public function __construct(
		public readonly string $directory,
		public readonly ?string $upstream,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): bool
	{
		return $worktreeRepository->setUpstream($this->directory, $this->upstream);
	}
}
