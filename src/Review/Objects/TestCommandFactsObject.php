<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * What decides the command a lens runs: the command set on the lens, and
 * whether the project defines its own way of testing as a composer script.
 */
final class TestCommandFactsObject extends ValueObject
{
	public function __construct(
		public readonly ?string $lensCommand,
		public readonly bool $hasComposerTestScript,
	) {
		parent::__construct();
	}
}
