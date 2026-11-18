<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * The background tasks on the working copy of a card: the one that runs or
 * ran last, how many wait behind it, and how the finished ones ended that the
 * panel has not shown yet.
 */
class WorkingCopyTaskStateObject extends ValueObject
{
	/**
	 * @param  array<string, string>  $result  what the task brings back for the panel, such as a written commit message
	 * @param  ?string  $current  the task that runs now, as its process reads it
	 * @param  list<string>  $queued  the tasks waiting their turn, as their process carries them
	 * @param  list<array{task: string, failure: ?string, notice: ?string, result: array<string, string>}>  $endings
	 */
	public function __construct(
		public readonly WorkingCopyTaskEnum $task,
		public readonly bool $isRunning,
		public readonly CarbonImmutable $startedAt,
		public readonly ?string $failure,
		public readonly ?string $notice = null,
		public readonly array $result = [],
		public readonly ?string $next = null,
		public readonly ?string $current = null,
		public readonly array $queued = [],
		public readonly array $endings = [],
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
			next: $data['next'] ?? null,
			current: $data['current'] ?? null,
			queued: array_values((array) ($data['queued'] ?? [])),
			endings: array_values((array) ($data['endings'] ?? [])),
		);
	}

	/**
	 * Whether a task of this kind runs now or waits its turn.
	 */
	public function isPending(WorkingCopyTaskEnum $workingCopyTaskEnum): bool
	{
		if (! $this->isRunning) {
			return false;
		}

		return $this->task === $workingCopyTaskEnum || collect($this->queued)
			->contains(fn (string $argument): bool => WorkingCopyTaskObject::fromArgument($argument)->task === $workingCopyTaskEnum);
	}

	/**
	 * Whether a finished task failed that the panel has not shown yet.
	 */
	public function hasUnshownFailure(): bool
	{
		return collect($this->endings)->contains(fn (array $ending): bool => ($ending['failure'] ?? null) !== null);
	}
}
