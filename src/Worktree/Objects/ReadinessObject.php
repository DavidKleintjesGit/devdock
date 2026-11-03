<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\ProjectKindEnum;

/**
 * The checklist of a working copy: every point that applies to this kind of
 * project, and when it was read.
 */
class ReadinessObject extends ValueObject
{
	/**
	 * @param  list<SetupCheckObject>  $setupChecks
	 */
	public function __construct(
		public readonly string $directory,
		public readonly ?ProjectKindEnum $projectKind,
		public readonly array $setupChecks,
		public readonly CarbonImmutable $checkedAt,
	) {
		parent::__construct();
	}

	public function isReady(): bool
	{
		return $this->openChecks() === [];
	}

	/**
	 * Every point that is not ready, waiting ones included.
	 *
	 * @return list<SetupCheckObject>
	 */
	public function openChecks(): array
	{
		return array_values(array_filter(
			$this->setupChecks,
			fn (SetupCheckObject $setupCheckObject): bool => ! $setupCheckObject->isReady(),
		));
	}

	public function openCount(): int
	{
		return count($this->openChecks());
	}

	public function totalCount(): int
	{
		return count($this->setupChecks);
	}

	public function canRepairAny(): bool
	{
		foreach ($this->setupChecks as $setupCheckObject) {
			if ($setupCheckObject->canRepair()) {
				return true;
			}
		}

		return false;
	}
}
