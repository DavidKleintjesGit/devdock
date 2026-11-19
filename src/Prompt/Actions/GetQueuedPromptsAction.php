<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Objects\QueuedPromptObject;

/**
 * The messages that wait for the agent of a card, first in line first.
 *
 * @method static list<QueuedPromptObject> execute(string $card)
 */
class GetQueuedPromptsAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	/**
	 * @return list<QueuedPromptObject>
	 */
	public function handle(): array
	{
		return array_values(array_map(
			fn (array $queued): QueuedPromptObject => QueuedPromptObject::from($queued),
			array_filter((array) Cache::get(SendPromptAction::keyFor($this->card), []), 'is_array'),
		));
	}
}
