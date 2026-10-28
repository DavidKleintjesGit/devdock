<?php

declare(strict_types=1);

namespace Src\Project\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A git checkout as the scan found it on disk.
 */
class ProjectObject extends ValueObject
{
	public function __construct(
		public readonly string $name,
		public readonly string $path,
		public readonly ?string $branch,
		public readonly int $changedFiles,
		public readonly int $ahead,
		public readonly int $behind,
		public readonly ?string $remote,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			name: (string) $data['name'],
			path: (string) $data['path'],
			branch: $data['branch'] ?? null,
			changedFiles: (int) $data['changed_files'],
			ahead: (int) $data['ahead'],
			behind: (int) $data['behind'],
			remote: $data['remote'] ?? null,
		);
	}
}
