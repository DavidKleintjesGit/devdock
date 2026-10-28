<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunObject;

/**
 * Sets a prompt ready and has it carried out outside the request. The
 * progress runs through the row the command updates.
 *
 * @method static PromptRunEntity execute(PromptRunObject $promptRunObject)
 */
class StartPromptAction extends Command
{
	public function __construct(
		public readonly PromptRunObject $promptRunObject,
	) {
	}

	public function handle(
		PromptRunRepositoryContract $promptRunRepository,
		ClaudeProcessRepositoryContract $claudeProcessRepository,
	): PromptRunEntity {
		$promptRunEntity = $promptRunRepository->create($this->promptRunObject);

		$claudeProcessRepository->startDetached($promptRunEntity->promptRunId);

		return $promptRunEntity;
	}
}
