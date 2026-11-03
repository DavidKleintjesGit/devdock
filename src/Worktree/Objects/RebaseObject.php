<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * The last rebase DevDock did in a working copy: the commit the branch stood
 * on before, so that state can always be found back.
 */
class RebaseObject extends ValueObject
{
	public function __construct(
		public readonly string $shaBefore,
		public readonly CarbonImmutable $at,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			shaBefore: (string) $data['sha_before'],
			at: CarbonImmutable::parse((string) $data['at']),
		);
	}
}
