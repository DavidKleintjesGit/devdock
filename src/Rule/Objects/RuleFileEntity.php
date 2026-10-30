<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Enums\RuleOriginEnum;

/**
 * A rule file in a project, with how it stands against mtvtd-core.
 */
class RuleFileEntity extends ValueObject
{
	public function __construct(
		public readonly RuleFileId $ruleFileId,
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
		public readonly ?CarbonImmutable $scannedAt,
	) {
		parent::__construct();
	}

	public function name(): string
	{
		return basename($this->path);
	}

	public function isBehindCore(): bool
	{
		return $this->origin === RuleOriginEnum::BEHIND_CORE;
	}

	/**
	 * How many lines this file is behind its counterpart in core.
	 */
	public function linesBehind(): ?int
	{
		return $this->coreLines === null ? null : $this->coreLines - $this->lines;
	}

	public function matches(string $term): bool
	{
		return str_contains(strtolower($this->path . ' ' . $this->project), strtolower($term));
	}
}
