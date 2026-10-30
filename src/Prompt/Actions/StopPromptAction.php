<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;

/**
 * Breaks off the round that is running on a task. What the agent already did
 * in the working copy stays where it is; only the process stops.
 *
 * @method static ?PromptRunEntity execute(string $card)
 */
class StopPromptAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
	): ?PromptRunEntity {
		$promptRunEntity = $promptRunRepository->getLatestForCard($this->card);

		if ($promptRunEntity === null || ! $promptRunEntity->isRunning()) {
			return $promptRunEntity;
		}

		$claudeProcessRepository->stop($promptRunEntity->promptRunId);

		return $promptRunRepository->markFailed(
			$promptRunEntity->promptRunId,
			__('Door jou afgebroken.'),
		);
	}
}
