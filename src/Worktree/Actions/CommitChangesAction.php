<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Writes what a round left behind down as one commit. It stays on the machine
 * until someone pushes it.
 *
 * Returns why it did not work, or null when it did.
 *
 * @method static ?string execute(string $directory, string $title, string $body = '', ?array $files = null)
 */
class CommitChangesAction extends Command
{
	public function __construct(
		public readonly string $directory,
		public readonly string $title,
		public readonly string $body = '',
		public readonly ?array $files = null,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): ?string
	{
		return $worktreeRepository->commit($this->directory, $this->title, $this->body, $this->files);
	}
}
