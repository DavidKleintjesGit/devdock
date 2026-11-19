<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Objects\QueuedPromptObject;
use Src\Review\Objects\ReviewRoundId;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Hands the first message of the queue to the agent once it is free. A round
 * that was about to go to the lenses travels with that message, so the
 * lenses go once the agent is through the queue. Returns whether a message
 * went out.
 *
 * @method static bool execute(string $card, ?ReviewRoundId $reviewRoundId = null)
 */
class StartNextQueuedPromptAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ?ReviewRoundId $reviewRoundId = null,
	) {}

	public function handle(): bool
	{
		return Cache::lock(SendPromptAction::lockFor($this->card), 10)->block(5, function (): bool {
			$queued = (array) Cache::get(SendPromptAction::keyFor($this->card), []);

			if ($queued === [] || SendPromptAction::isAgentBusy($this->card)) {
				return false;
			}

			$next = QueuedPromptObject::from((array) array_shift($queued));
			$queued === [] ? Cache::forget(SendPromptAction::keyFor($this->card)) : Cache::forever(SendPromptAction::keyFor($this->card), $queued);

			$workingCopyTaskObject = $next->workingCopyTaskObject;

			if ($this->reviewRoundId !== null && $workingCopyTaskObject->reviewRoundId === null) {
				$workingCopyTaskObject = WorkingCopyTaskObject::from([
					...(array) json_decode((string) base64_decode($workingCopyTaskObject->toArgument()), true),
					'review_round_id' => $this->reviewRoundId->id,
				]);
			}

			StartWorkingCopyTaskAction::execute($workingCopyTaskObject);

			return true;
		});
	}
}
