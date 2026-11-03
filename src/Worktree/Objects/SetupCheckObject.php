<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\CheckStateEnum;
use Src\Worktree\Enums\SetupCheckEnum;

/**
 * Where one point of the checklist stands, what was found, and what a repair
 * would do; no repair when DevDock cannot fix it on its own.
 */
class SetupCheckObject extends ValueObject
{
	public function __construct(
		public readonly SetupCheckEnum $setupCheck,
		public readonly CheckStateEnum $state,
		public readonly string $detail,
		public readonly ?string $repair = null,
	) {
		parent::__construct();
	}

	public function isReady(): bool
	{
		return $this->state === CheckStateEnum::READY;
	}

	public function canRepair(): bool
	{
		return $this->state === CheckStateEnum::OPEN && $this->repair !== null;
	}
}
