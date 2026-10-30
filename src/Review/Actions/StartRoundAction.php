<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Facades\Bus;
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\LensProcessRepositoryContract;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;
use Src\Worktree\Contracts\WorktreeRepositoryContract;

/**
 * Sends the lenses over a delivery. Only the lenses whose paths are touched
 * are called; the rest are written down as skipped so the round shows what it
 * did and did not look at.
 *
 * A round runs at every delivery, not at every prompt.
 *
 * @method static ?ReviewRoundEntity execute(string $card, ?string $directory)
 */
class StartRoundAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $directory,
	) {}

	public function handle(
		ReviewRoundRepositoryContract $reviewRoundRepository,
		LensRepositoryContract $lensRepository,
		LensProcessRepositoryContract $lensProcessRepository,
		WorktreeRepositoryContract $worktreeRepository,
	): ?ReviewRoundEntity {
		$cardObject = collect(BuildBoardAction::execute())
			->first(fn (CardObject $cardObject) => $cardObject->id === $this->card);

		if ($cardObject === null || $this->directory === null) {
			return null;
		}

		$rounds = $reviewRoundRepository->getForCard($this->card);
		$standing = $this->running($rounds);

		if ($standing !== null) {
			return $standing;
		}

		if ($rounds->count() >= (int) config('devdock.review.max_rounds')) {
			return null;
		}

		$files = $worktreeRepository->getChangedFiles($this->directory);

		if ($files === []) {
			return null;
		}

		WriteAgentContextAction::execute($this->card, $this->directory);

		// the assignments and reports of the round before are about a commit
		// that has moved on
		resolve(AgentContextRepositoryContract::class)->clearRound($this->directory);

		$called = $lensRepository->getAll($cardObject->project)
			->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled);

		$lenses = [];

		foreach ($called as $lensEntity) {
			$touches = $lensEntity->touches($files);

			$lenses[] = [
				'lens_id' => $lensEntity->lensId,
				'verdict' => $touches === [] ? VerdictEnum::SKIPPED : VerdictEnum::WAITING,
				'touched_files' => count($touches),
			];
		}

		$reviewRoundEntity = $reviewRoundRepository->open(
			$this->card,
			$worktreeRepository->getHead($this->directory),
			$this->directory,
			$lenses,
		);

		$jobs = [];

		foreach ($reviewRoundEntity->results as $resultEntity) {
			if ($resultEntity->verdict === VerdictEnum::WAITING) {
				$jobs[] = new RunLensAction($reviewRoundEntity->reviewRoundId, $resultEntity->lensId);
			}
		}

		// every lens may have been skipped, and then the round is already over
		if ($jobs === []) {
			return $reviewRoundRepository->settle($reviewRoundEntity->reviewRoundId);
		}

		$roundId = (int) $reviewRoundEntity->reviewRoundId->id;

		Bus::batch($jobs)
			->name(__('ronde :number', ['number' => $reviewRoundEntity->number]) . ' · ' . $this->card)
			->onQueue('lenses')
			->finally(fn () => SettleRoundAction::execute(new ReviewRoundId(id: $roundId)))
			->dispatch();

		$lensProcessRepository->startWorkers(count($jobs));

		return $reviewRoundEntity;
	}

	private function running(iterable $rounds): ?ReviewRoundEntity
	{
		foreach ($rounds as $reviewRoundEntity) {
			if ($reviewRoundEntity->isRunning()) {
				return $reviewRoundEntity;
			}
		}

		return null;
	}
}
