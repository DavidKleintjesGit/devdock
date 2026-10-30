<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Objects\ReviewRoundEntityCollection;

/**
 * Every review round of one task, oldest first.
 *
 * @method static ReviewRoundEntityCollection execute(string $card)
 */
class GetRoundsAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(ReviewRoundRepositoryContract $reviewRoundRepository): ReviewRoundEntityCollection
	{
		return $reviewRoundRepository->getForCard($this->card);
	}
}
