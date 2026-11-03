<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\SetupCheckEnum;

/**
 * The last repair of a working copy: what it was busy with, whether it is
 * still running, and why it stopped when it failed.
 */
class RepairStateObject extends ValueObject
{
	public function __construct(
		public readonly bool $isRunning,
		public readonly ?SetupCheckEnum $setupCheck,
		public readonly ?string $step,
		public readonly CarbonImmutable $startedAt,
		public readonly ?CarbonImmutable $finishedAt,
		public readonly ?string $failure,
		public readonly string $logPath,
	) {
		parent::__construct();
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			isRunning: (bool) ($data['is_running'] ?? false),
			setupCheck: SetupCheckEnum::tryFrom((string) ($data['setup_check'] ?? '')),
			step: $data['step'] ?? null,
			startedAt: CarbonImmutable::parse((string) ($data['started_at'] ?? 'now')),
			finishedAt: isset($data['finished_at']) ? CarbonImmutable::parse((string) $data['finished_at']) : null,
			failure: $data['failure'] ?? null,
			logPath: (string) ($data['log_path'] ?? ''),
		);
	}

	/**
	 * What the log of this repair says, its last lines when it is long.
	 */
	public function logTail(int $lines = 40): string
	{
		$log = is_file($this->logPath) ? (string) file_get_contents($this->logPath) : '';

		return implode("\n", array_slice(preg_split('/\R/', trim($log)) ?: [], -$lines));
	}
}
