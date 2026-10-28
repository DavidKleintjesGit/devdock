<?php

declare(strict_types=1);

namespace Src\Project\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A git checkout in the sites folder, with the standing of its working tree.
 */
class ProjectEntity extends ValueObject
{
	public function __construct(
		public readonly ProjectId $projectId,
		public readonly string $name,
		public readonly string $path,
		public readonly ?string $branch,
		public readonly int $changedFiles,
		public readonly int $ahead,
		public readonly int $behind,
		public readonly ?string $remote,
		public readonly ?CarbonImmutable $scannedAt,
	) {
		parent::__construct();
	}

	/**
	 * The ticket the branch points at.
	 */
	public function ticketKey(): ?string
	{
		preg_match('/CSM-\d{3,5}/', (string) $this->branch, $found);

		return $found[0] ?? null;
	}

	/**
	 * Whether this project belongs to the given Bitbucket workspace. The remote
	 * decides, not the folder name: a folder you rename locally keeps pointing
	 * at the same workspace.
	 */
	public function belongsToWorkspace(string $workspace): bool
	{
		return filled($workspace)
			&& str_contains((string) $this->remote, 'bitbucket.org')
			&& str_contains((string) $this->remote, $workspace);
	}

	/**
	 * The name of the repository in the remote. It often differs from the folder
	 * name a checkout has on disk.
	 */
	public function repositorySlug(): ?string
	{
		if (blank($this->remote)) {
			return null;
		}

		$last = basename(str_replace(':', '/', (string) $this->remote));

		return str_ends_with($last, '.git') ? substr($last, 0, -4) : $last;
	}
}
