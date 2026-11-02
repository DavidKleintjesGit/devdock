<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Actions\ReadReportAction;
use Src\Prompt\Actions\ReadTranscriptAction;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Review\Objects\RoundTurnObject;

/**
 * The agent turn behind every round of a task, by round, so a round can be
 * checked as a whole: what the lenses found and what the agent did with it.
 *
 * @method static array execute(string $card)
 */
class GetRoundTurnsAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	/**
	 * @return array<int, RoundTurnObject>
	 */
	public function handle(PromptRunRepositoryContract $promptRunRepository): array
	{
		$turns = [];

		foreach ($promptRunRepository->getByReviewRounds($this->card) as $roundId => $promptRunEntity) {
			$transcriptObject = ReadTranscriptAction::execute($promptRunRepository->logPath($promptRunEntity->promptRunId), $promptRunEntity->directory);

			$turns[$roundId] = new RoundTurnObject(
				run: $promptRunEntity,
				report: ReadReportAction::execute($promptRunEntity->output),
				changes: $transcriptObject?->changedFiles() ?? [],
			);
		}

		return $turns;
	}
}
