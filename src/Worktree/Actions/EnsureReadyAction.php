<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Carbon\CarbonImmutable;
use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Worktree\Enums\WorkKindEnum;
use Src\Worktree\Exceptions\SetupIncompleteException;
use Src\Worktree\Exceptions\WorkingCopyBusyException;
use Src\Worktree\Objects\ReadinessObject;

/**
 * The gate in front of every piece of work Claude Code does for a task: the
 * working copy has to pass its whole checklist, read fresh, and no work may
 * run in it that this kind of work may not run alongside. An agent turn waits
 * for the lenses and for another turn; a round waits for the agent.
 *
 * A turn or round that started longer ago than it may run fell over without
 * writing its end, and holds nothing up.
 *
 * @method static ReadinessObject execute(?string $directory, WorkKindEnum $workKind)
 *
 * @throws SetupIncompleteException
 * @throws WorkingCopyBusyException
 */
class EnsureReadyAction extends Command
{
	public function __construct(
		public readonly ?string $directory,
		public readonly WorkKindEnum $workKind,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ReviewRoundRepositoryContract $reviewRoundRepository,
	): ReadinessObject {
		// a lens follows the round that just read the list thoroughly, and keeps the migration count it found
		$readinessObject = CheckReadinessAction::execute($this->directory, $this->workKind !== WorkKindEnum::LENS);
		GetReadinessAction::forget($this->directory);

		if (! $readinessObject->isReady()) {
			throw new SetupIncompleteException($readinessObject);
		}

		$directory = CheckReadinessAction::normalize($readinessObject->directory);

		if ($this->workKind->waitsForAgent()) {
			$since = CarbonImmutable::now()->subSeconds((int) config('devdock.prompt_timeout'));
			$isAgentBusy = collect($promptRunRepository->getRunningSince($since))
				->contains(fn (PromptRunEntity $promptRunEntity): bool => CheckReadinessAction::normalize((string) $promptRunEntity->directory) === $directory);

			if ($isAgentBusy) {
				throw new WorkingCopyBusyException(__('De agent werkt nog in deze werkkopie. Wacht tot zijn beurt klaar is.'));
			}
		}

		if ($this->workKind->waitsForLenses()) {
			// the lenses of a round queue for the workers, so a round may run twice as long as one lens
			$since = CarbonImmutable::now()->subSeconds(2 * (int) config('devdock.review.lens_timeout'));
			$areLensesBusy = collect($reviewRoundRepository->getRunning())
				->contains(fn (ReviewRoundEntity $reviewRoundEntity): bool => CheckReadinessAction::normalize((string) $reviewRoundEntity->directory) === $directory
					&& ($reviewRoundEntity->startedAt === null || $reviewRoundEntity->startedAt->greaterThanOrEqualTo($since)));

			if ($areLensesBusy) {
				throw new WorkingCopyBusyException(__('De lenzen kijken nog in deze werkkopie. Wacht tot de ronde klaar is.'));
			}
		}

		return $readinessObject;
	}
}
