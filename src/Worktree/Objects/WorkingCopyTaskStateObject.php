<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * The last background task on the working copy of a card: whether it still
 * runs, and why it stopped when it failed.
 */
class WorkingCopyTaskStateObject extends ValueObject
{
	/**
	 * @param  array<string, string>  $result  what the task brings back for the panel, such as a written commit message
	 */
	public function __construct(
		public readonly WorkingCopyTaskEnum $task,
		public readonly bool $isRunning,
		public readonly CarbonImmutable $startedAt,
		public readonly ?string $failure,
		public readonly ?string $notice = null,
		public readonly array $result = [],
	) {
		parent::__construct();
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			task: WorkingCopyTaskEnum::from((string) $data['task']),
			isRunning: (bool) ($data['is_running'] ?? false),
			startedAt: CarbonImmutable::parse((string) ($data['started_at'] ?? 'now')),
			failure: $data['failure'] ?? null,
			notice: $data['notice'] ?? null,
			result: (array) ($data['result'] ?? []),
		);
	}
}
