<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One piece of what an agent or a lens is given: a file, or a text DevDock
 * puts together, with where it comes from.
 */
class ContextFileObject extends ValueObject
{
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly ?string $group = null,
		public readonly ?string $description = null,
		public readonly ?string $path = null,
		public readonly ?string $contents = null,
		// the card tab where this is read in full, when it is not a text of its own
		public readonly ?string $tab = null,
	) {
		parent::__construct();
	}
}
