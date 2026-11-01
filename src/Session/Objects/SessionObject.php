<?php

declare(strict_types=1);

namespace Src\Session\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;

/**
 * A Claude Code session file as the scan found it on disk.
 */
class SessionObject extends ValueObject
{
	public function __construct(
		public readonly string $uuid,
		public readonly string $folder,
		public readonly ?string $cwd,
		public readonly ?string $project,
		public readonly ?string $title,
		public readonly ?string $branch,
		public readonly int $promptCount,
		public readonly int $bytes,
		public readonly CarbonImmutable $lastActiveAt,
		public readonly ?CarbonImmutable $firstActiveAt,
		public readonly int $activeDays,
		public readonly bool $hasCommit,
		public readonly bool $isInterrupted,
		public readonly bool $isUnanswered,
		public readonly string $fingerprint,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			uuid: (string) $data['uuid'],
			folder: (string) $data['folder'],
			cwd: $data['cwd'] ?? null,
			project: $data['project'] ?? null,
			title: $data['title'] ?? null,
			branch: $data['branch'] ?? null,
			promptCount: (int) $data['prompt_count'],
			bytes: (int) $data['bytes'],
			lastActiveAt: CarbonImmutable::parse($data['last_active_at']),
			firstActiveAt: isset($data['first_active_at']) ? CarbonImmutable::parse($data['first_active_at']) : null,
			activeDays: (int) ($data['active_days'] ?? 1),
			hasCommit: (bool) ($data['has_commit'] ?? false),
			isInterrupted: (bool) ($data['is_interrupted'] ?? false),
			isUnanswered: (bool) ($data['is_unanswered'] ?? false),
			fingerprint: (string) $data['fingerprint'],
		);
	}
}
