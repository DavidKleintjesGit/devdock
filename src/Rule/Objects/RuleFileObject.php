<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Enums\RuleOriginEnum;

/**
 * A rule file as the scan found it in a project.
 */
class RuleFileObject extends ValueObject
{
	public function __construct(
		public readonly string $project,
		public readonly string $path,
		public readonly ?string $summary,
		public readonly RuleGroupEnum $group,
		public readonly RuleOriginEnum $origin,
		public readonly int $lines,
		public readonly int $words,
		public readonly int $bytes,
		public readonly ?string $coreFile,
		public readonly ?int $coreLines,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			project: (string) $data['project'],
			path: (string) $data['path'],
			summary: $data['summary'] ?? null,
			group: RuleGroupEnum::from((string) $data['group']),
			origin: RuleOriginEnum::from((string) $data['origin']),
			lines: (int) $data['lines'],
			words: (int) $data['words'],
			bytes: (int) $data['bytes'],
			coreFile: $data['core_file'] ?? null,
			coreLines: isset($data['core_lines']) ? (int) $data['core_lines'] : null,
		);
	}
}
