<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Facades\Cache;
use Src\Packages\Cqrs\Command;
use Src\Review\Objects\ReviewRoundId;
use Src\Review\Objects\RoundChoicesObject;

/**
 * Your choices on a round so far, or null when you made none yet.
 *
 * @method static ?RoundChoicesObject execute(ReviewRoundId $reviewRoundId)
 */
class GetRoundChoicesAction extends Command
{
	public const KEY = 'review.round.choices.';

	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
	) {}

	public function handle(): ?RoundChoicesObject
	{
		$kept = Cache::get(self::KEY . $this->reviewRoundId->id);

		return is_array($kept) ? RoundChoicesObject::from($kept) : null;
	}
}
