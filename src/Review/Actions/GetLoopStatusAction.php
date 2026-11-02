<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Enums\ReportStatusEnum;
use Src\Review\Enums\LoopStatusEnum;
use Src\Review\Enums\RoundStatusEnum;
use Src\Review\Objects\ReviewRoundEntity;
use Src\Review\Objects\RoundTurnObject;

/**
 * Reads where the loop stands from its latest round and the agent turn on it.
 *
 * @method static LoopStatusEnum execute(?ReviewRoundEntity $latestRound, ?RoundTurnObject $latestTurn, int $maxRounds)
 */
class GetLoopStatusAction extends Command
{
	public function __construct(
		public readonly ?ReviewRoundEntity $latestRound,
		public readonly ?RoundTurnObject $latestTurn,
		public readonly int $maxRounds,
	) {}

	public function handle(): LoopStatusEnum
	{
		$latestRound = $this->latestRound;

		return match (true) {
			$latestRound === null => LoopStatusEnum::IDLE,
			$latestRound->isRunning() => LoopStatusEnum::LENSES,
			$latestRound->status === RoundStatusEnum::STOPPED => LoopStatusEnum::BROKEN_OFF,
			$latestRound->openFindings() === 0 && $latestRound->fallenLenses() > 0 => LoopStatusEnum::LENS_FELL,
			$latestRound->status === RoundStatusEnum::PASSED || $latestRound->openFindings() === 0 => LoopStatusEnum::CLEAN,
			$this->latestTurn?->run->isRunning() === true => LoopStatusEnum::AGENT,
			$this->latestTurn !== null && $this->latestTurn->report?->status !== ReportStatusEnum::DONE => LoopStatusEnum::AGENT_ASKS,
			$latestRound->positionInSeries() >= $this->maxRounds => LoopStatusEnum::STOPPED,
			default => LoopStatusEnum::WAITING,
		};
	}
}
