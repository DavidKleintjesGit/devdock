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
		public readonly ?string $project,
		public readonly ?string $title,
		public readonly ?string $branch,
		public readonly int $promptCount,
		public readonly int $bytes,
		public readonly CarbonImmutable $lastActiveAt,
		public readonly string $fingerprint,
	) {
		parent::__construct();
	}

	public static function from(array $data): self
	{
		return new self(
			uuid: (string) $data['uuid'],
			folder: (string) $data['folder'],
			project: $data['project'] ?? null,
			title: $data['title'] ?? null,
			branch: $data['branch'] ?? null,
			promptCount: (int) $data['prompt_count'],
			bytes: (int) $data['bytes'],
			lastActiveAt: CarbonImmutable::parse($data['last_active_at']),
			fingerprint: (string) $data['fingerprint'],
		);
	}
}
