<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * A message that waits until the agent's current turn is done, in the order it was sent.
 */
final class QueuedPromptObject extends ValueObject
{
	public function __construct(
		public readonly QueuedPromptId $queuedPromptId,
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {
		parent::__construct();
	}

	/**
	 * @param  array{uuid: string, task: string}  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			new QueuedPromptId(uuid: $data['uuid']),
			WorkingCopyTaskObject::fromArgument($data['task']),
		);
	}

	public function prompt(): string
	{
		return (string) $this->workingCopyTaskObject->prompt;
	}

	/**
	 * Whether it hands findings of a review round to the agent.
	 */
	public function carriesFindings(): bool
	{
		return $this->workingCopyTaskObject->reviewRoundId !== null;
	}
}
