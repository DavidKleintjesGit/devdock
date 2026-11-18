<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\RoundChoicesObject;

/**
 * Keeps your choices on a round until they go to the agent, so they survive
 * closing the card, opening another one or reloading the page. Without
 * choices the round forgets them.
 *
 * @method static void execute(ReviewRoundId $reviewRoundId, ?RoundChoicesObject $roundChoicesObject)
 */
class KeepRoundChoicesAction extends Command
{
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
		public readonly ?RoundChoicesObject $roundChoicesObject,
	) {}

	public function handle(): void
	{
		$key = GetRoundChoicesAction::KEY . $this->reviewRoundId->id;

		if ($this->roundChoicesObject === null) {
			Cache::forget($key);

			return;
		}

		Cache::forever($key, [
			'choices' => $this->roundChoicesObject->choices,
			'reasons' => $this->roundChoicesObject->reasons,
			'message' => $this->roundChoicesObject->message,
		]);
	}
}
