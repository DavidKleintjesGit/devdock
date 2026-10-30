<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\VerdictEnum;
use Src\Review\Objects\ReviewResultEntity;
use Src\Review\Objects\ReviewRoundEntity;

/**
 * Lays down what came of a round, one report per lens. Every lens leaves one,
 * also the ones that approved and the ones that never had to look: together
 * they are the record of who judged what, so nothing has to be guessed from
 * an absent file.
 *
 * @method static int execute(ReviewRoundEntity $reviewRoundEntity)
 */
class WriteRoundReportsAction extends Command
{
	public function __construct(
		public readonly ReviewRoundEntity $reviewRoundEntity,
	) {}

	public function handle(AgentContextRepositoryContract $agentContextRepository): int
	{
		$directory = $this->reviewRoundEntity->directory;

		if ($directory === null || ! is_dir($directory)) {
			return 0;
		}

		$written = 0;

		foreach ($this->reviewRoundEntity->results as $resultEntity) {
			if ($agentContextRepository->writeReport($directory, $resultEntity->lensName, $this->report($resultEntity))) {
				$written++;
			}
		}

		$agentContextRepository->exclude($directory);

		return $written;
	}

	private function report(ReviewResultEntity $resultEntity): string
	{
		return implode("\n\n", array_filter([
			'# ' . $resultEntity->lensName,
			$this->opening($resultEntity),
			trim((string) $resultEntity->report),
		]));
	}

	/**
	 * The line that says what this lens did, so a report can be read without
	 * the round it belongs to being at hand.
	 */
	private function opening(ReviewResultEntity $resultEntity): string
	{
		$where = sprintf(
			'Round %d, on commit %s.',
			$this->reviewRoundEntity->number,
			$this->reviewRoundEntity->commitSha ?? '-',
		);

		return $where . ' ' . match ($resultEntity->verdict) {
			VerdictEnum::PASS => sprintf('Looked at %d changed file(s) and approved.', $resultEntity->touchedFiles),
			VerdictEnum::FAIL => sprintf(
				'Looked at %d changed file(s) and left %d point(s).',
				$resultEntity->touchedFiles,
				$resultEntity->findings,
			),
			VerdictEnum::SKIPPED => 'Did not look: no changed file on its paths.',
			VerdictEnum::ERROR => 'Fell over before it reached a verdict.',
			default => 'Has not finished.',
		};
	}
}
