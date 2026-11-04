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
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Exceptions\RoundNotStartedException;
use Src\Review\Objects\LensEntity;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\ReviewRoundId;
use Src\Worktree\Actions\EnsureOnBranchAction;
use Src\Worktree\Actions\EnsureReadyAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\WorkKindEnum;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;

/**
 * Sends the lenses over a delivery. Only the lenses whose paths are touched
 * are called; the rest are written down as skipped so the round shows what it
 * did and did not look at.
 *
 * A round runs at every delivery, not at every prompt.
 *
 * A loop holds at most the rounds the workflow allows. Restarting it starts a
 * new series, after you looked at what went wrong; so does the first round
 * after a clean or broken-off one.
 *
 * @method static ?ReviewRoundEntity execute(string $card, ?string $directory, bool $startsSeries = false)
 *
 * @throws WorktreeNotPossibleException
 * @throws RoundNotStartedException
 * @throws SetupIncompleteException
 * @throws WorkingCopyBusyException
 */
class StartRoundAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $directory,
		public readonly bool $startsSeries = false,
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

		if ($cardObject->branch !== null) {
			EnsureOnBranchAction::execute($this->directory, $cardObject->branch);
		}

		$rounds = $reviewRoundRepository->getForCard($this->card);
		$standing = $this->running($rounds);

		if ($standing !== null) {
			return $standing;
		}

		EnsureReadyAction::execute($this->directory, WorkKindEnum::ROUND);

		$latest = collect($rounds)->last();

		// a round that left nothing open, or that you broke off, ends its loop, so the next one begins a new series
		$startsSeries = $this->startsSeries
			|| $latest?->openFindings() === 0
			|| $latest?->status === RoundStatusEnum::STOPPED;
		$maxRounds = GetWorkflowSettingAction::execute($cardObject->project)->maxRounds;

		if (! $startsSeries && $latest !== null && $latest->positionInSeries() >= $maxRounds) {
			throw RoundNotStartedException::maximumReached($maxRounds);
		}

		$files = $worktreeRepository->getChangedFiles($this->directory);

		if ($files === []) {
			throw RoundNotStartedException::nothingChanged();
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
			$worktreeRepository->getFingerprint($this->directory),
			$startsSeries,
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
			->onConnection('lenses')
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
