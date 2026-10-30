<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

final class LensId extends ValueObject
{
	public function __construct(
		public readonly ?int $id = null,
	) {
		parent::__construct();
	}
}
