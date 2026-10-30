<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;

/**
 * Breaks off a running round. Lenses that were still waiting are written down
 * as skipped, so the round says what it did get to.
 *
 * @method static ReviewRoundEntity execute(ReviewRoundId $reviewRoundId)
 */
class StopRoundAction extends Command
{
	public function __construct(
		public readonly ReviewRoundId $reviewRoundId,
	) {}

	public function handle(ReviewRoundRepositoryContract $reviewRoundRepository): ReviewRoundEntity
	{
		return $reviewRoundRepository->stop($this->reviewRoundId);
	}
}
