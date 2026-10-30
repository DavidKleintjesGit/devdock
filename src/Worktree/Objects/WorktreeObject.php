<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The working copy a task asks for: one branch of one project, in a folder of
 * its own.
 */
class WorktreeObject extends ValueObject
{
	public function __construct(
		public readonly string $project,
		public readonly string $repositoryPath,
		public readonly string $branch,
		public readonly string $path,
		public readonly ?string $site,
		public readonly ?string $card,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			project: (string) $data['project'],
			repositoryPath: (string) $data['repository_path'],
			branch: (string) $data['branch'],
			path: (string) $data['path'],
			site: $data['site'] ?? null,
			card: $data['card'] ?? null,
		);
	}
}
