<?php

declare(strict_types=1);

namespace Src\Skill\Objects;

use Src\Packages\ValueObject\ValueObject;

final class SkillId extends ValueObject
{
	public function __construct(
		public readonly ?int $id = null,
		public readonly ?string $uuid = null,
	) {
		parent::__construct();
	}
}
