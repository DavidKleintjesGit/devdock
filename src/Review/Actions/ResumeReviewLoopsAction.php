<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Carbon\CarbonImmutable;
use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;

/**
 * Picks up a review loop that lost its way: a round whose lenses all spoke
 * but that was never closed, or a failed round that never reached the agent.
 * Runs with every fetch round, so a loop is never stuck for longer than that.
 *
 * Only recent rounds count. An old failed round was left alone for a reason,
 * and starting an agent on it out of nowhere would surprise you.
 *
 * @method static void execute()
 */
class ResumeReviewLoopsAction extends Command
{
	private const RECENT_MINUTES = 60;

	public function handle(ReviewRoundRepositoryContract $reviewRoundRepository): void
	{
		$since = CarbonImmutable::now()->subMinutes(self::RECENT_MINUTES);

		foreach ($reviewRoundRepository->getLatestPerCard() as $reviewRoundEntity) {
			if ($reviewRoundEntity->isRunning()) {
				CloseRoundAction::execute($reviewRoundEntity->reviewRoundId);

				continue;
			}

			if ($reviewRoundEntity->status === RoundStatusEnum::FAILED && $reviewRoundEntity->finishedAt?->greaterThan($since)) {
				ContinueReviewAction::execute($reviewRoundEntity);
			}
		}
	}
}
