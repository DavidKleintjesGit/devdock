<?php

declare(strict_types=1);

namespace Src\Skill\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Skill\Enums\SkillScopeEnum;

/**
 * A skill as the scan found it on disk.
 */
class SkillObject extends ValueObject
{
	public function __construct(
		public readonly string $name,
		public readonly SkillScopeEnum $scope,
		public readonly ?string $project,
		public readonly ?string $plugin,
		public readonly ?string $description,
		public readonly string $path,
		public readonly int $lines,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			name: (string) $data['name'],
			scope: SkillScopeEnum::from((string) $data['scope']),
			project: $data['project'] ?? null,
			plugin: $data['plugin'] ?? null,
			description: $data['description'] ?? null,
			path: (string) $data['path'],
			lines: (int) $data['lines'],
		);
	}
}
