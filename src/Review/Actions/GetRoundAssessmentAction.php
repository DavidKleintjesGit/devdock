<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\GetQueuedPromptsAction;
use Src\Prompt\Objects\QueuedPromptObject;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Review\Contracts\ReviewFindingRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Objects\AssessedFindingObject;
use Src\Review\Objects\AssessmentObject;
use Src\Review\Objects\ReviewFindingEntity;
use Src\Review\Objects\RoundAssessmentObject;

/**
 * The round of a card that waits on your choice, when there is one: you
 * decide on the findings in this project, the last round found something,
 * and they are not on their way to the agent yet. When the last turn of the
 * agent assessed that round, its verdicts come along.
 *
 * @method static ?RoundAssessmentObject execute(string $card, ?string $project)
 */
class GetRoundAssessmentAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly ?string $project,
	) {}

	public function handle(
		ReviewRoundRepositoryContract $reviewRoundRepository,
		PromptRunRepositoryContract $promptRunRepository,
		ReviewFindingRepositoryContract $reviewFindingRepository,
	): ?RoundAssessmentObject {
		if (GetWorkflowSettingAction::execute($this->project)->reviewMode->fixesByItself()) {
			return null;
		}

		$reviewRoundEntity = $reviewRoundRepository->getLatestForCard($this->card);
		$promptRunEntity = $promptRunRepository->getLatestForCard($this->card);

		if ($reviewRoundEntity?->status !== RoundStatusEnum::FAILED) {
			return null;
		}

		$roundId = $reviewRoundEntity->reviewRoundId->id;
		$isTurnOnRound = $promptRunEntity !== null && $promptRunEntity->reviewRoundId === $roundId;
		$isHandedOver = collect(GetQueuedPromptsAction::execute($this->card))
			->contains(fn (QueuedPromptObject $queuedPromptObject): bool => $queuedPromptObject->workingCopyTaskObject->reviewRoundId?->id === $roundId);

		// a turn of the agent on this round, running or waiting in line, has the findings already
		if (($isTurnOnRound && $promptRunEntity->isRunning()) || $isHandedOver) {
			return null;
		}

		$assessmentsByFinding = collect($isTurnOnRound ? ReadAssessmentAction::execute($promptRunEntity->output) : [])
			->keyBy(fn (AssessmentObject $assessmentObject): int => (int) $assessmentObject->reviewFindingId->id);

		$findings = collect($reviewFindingRepository->getForCard($this->card))
			->filter(fn (ReviewFindingEntity $reviewFindingEntity): bool => $reviewFindingEntity->isOpen())
			->map(fn (ReviewFindingEntity $reviewFindingEntity): AssessedFindingObject => new AssessedFindingObject(
				$reviewFindingEntity,
				$assessmentsByFinding->get((int) $reviewFindingEntity->reviewFindingId->id),
			))
			->values()
			->all();

		return $findings === [] ? null : new RoundAssessmentObject($reviewRoundEntity->reviewRoundId, $reviewRoundEntity->positionInSeries(), $findings);
	}
}
