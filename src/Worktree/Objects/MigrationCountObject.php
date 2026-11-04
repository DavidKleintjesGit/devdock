<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * How many migrations have not run on the database of a working copy, or
 * why artisan could not tell.
 */
class MigrationCountObject extends ValueObject
{
	public function __construct(
		public readonly ?int $pending,
		public readonly ?string $failure,
	) {
		parent::__construct();
	}
}
