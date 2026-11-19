<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Actions\GetWorkingCopyTaskAction;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * Hands a message to the agent of a card: at once when it waits, and otherwise
 * at the back of the queue behind the turn it is busy with. Returns whether
 * the message waits in the queue.
 *
 * @method static bool execute(WorkingCopyTaskObject $workingCopyTaskObject)
 */
class SendPromptAction extends Command
{
	public const KEY = 'prompt.queue.';

	public const LOCK = 'prompt.queue.lock.';

	public function __construct(
		public readonly WorkingCopyTaskObject $workingCopyTaskObject,
	) {}

	public static function keyFor(string $card): string
	{
		return self::KEY . md5($card);
	}

	public static function lockFor(string $card): string
	{
		return self::LOCK . md5($card);
	}

	/**
	 * Whether the agent of a card is in a turn, or one is on its way.
	 */
	public static function isAgentBusy(string $card): bool
	{
		return GetLatestPromptRunAction::execute($card)?->isRunning() === true
			|| GetWorkingCopyTaskAction::execute($card)?->isPending(WorkingCopyTaskEnum::PROMPT) === true;
	}

	public function handle(): bool
	{
		$card = $this->workingCopyTaskObject->card;

		return Cache::lock(self::lockFor($card), 10)->block(5, function () use ($card): bool {
			$queued = (array) Cache::get(self::keyFor($card), []);

			// a queue that holds messages keeps its order, also when the agent just came free
			if ($queued !== [] || self::isAgentBusy($card)) {
				Cache::forever(self::keyFor($card), [...$queued, [
					'uuid' => (string) Str::uuid(),
					'task' => $this->workingCopyTaskObject->toArgument(),
				]]);

				return true;
			}

			StartWorkingCopyTaskAction::execute($this->workingCopyTaskObject);

			return false;
		});
	}
}
