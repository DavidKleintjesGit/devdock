<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * A task on the working copy of a card, with what it needs to run in a
 * process of its own.
 */
class WorkingCopyTaskObject extends ValueObject
{
	public function __construct(
		public readonly string $card,
		public readonly WorkingCopyTaskEnum $task,
		public readonly string $project,
		public readonly string $branch,
		public readonly ?DatabaseFillEnum $fill = null,
		public readonly bool $carriesChanges = false,
	) {
		parent::__construct();
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			card: (string) $data['card'],
			task: WorkingCopyTaskEnum::from((string) $data['task']),
			project: (string) $data['project'],
			branch: (string) $data['branch'],
			fill: DatabaseFillEnum::tryFrom((string) ($data['fill'] ?? '')),
			carriesChanges: (bool) ($data['carries_changes'] ?? false),
		);
	}

	/**
	 * The task as the command line carries it to the process that runs it.
	 */
	public function toArgument(): string
	{
		return base64_encode((string) json_encode([
			'card' => $this->card,
			'task' => $this->task->value,
			'project' => $this->project,
			'branch' => $this->branch,
			'fill' => $this->fill?->value,
			'carries_changes' => $this->carriesChanges,
		]));
	}

	public static function fromArgument(string $argument): self
	{
		return self::from((array) json_decode((string) base64_decode($argument), true));
	}
}
