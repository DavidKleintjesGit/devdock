<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Objects\QueuedPromptId;
use Src\Prompt\Objects\QueuedPromptObject;

/**
 * Takes one waiting message back out of the queue, to drop it or to write it
 * again. Null when it already went to the agent.
 *
 * @method static ?QueuedPromptObject execute(string $card, QueuedPromptId $queuedPromptId)
 */
class TakeQueuedPromptAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly QueuedPromptId $queuedPromptId,
	) {}

	public function handle(): ?QueuedPromptObject
	{
		return Cache::lock(SendPromptAction::lockFor($this->card), 10)->block(5, function (): ?QueuedPromptObject {
			$queued = (array) Cache::get(SendPromptAction::keyFor($this->card), []);
			$taken = null;
			$left = [];

			foreach ($queued as $entry) {
				if ($taken === null && is_array($entry) && ($entry['uuid'] ?? null) === $this->queuedPromptId->uuid) {
					$taken = QueuedPromptObject::from($entry);

					continue;
				}

				$left[] = $entry;
			}

			$left === [] ? Cache::forget(SendPromptAction::keyFor($this->card)) : Cache::forever(SendPromptAction::keyFor($this->card), $left);

			return $taken;
		});
	}
}
