<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Contracts\TailwindSetupRepositoryContract;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Objects\TailwindSetupObject;

/**
 * The Tailwind setup of the copy a turn worked in, for the sketches it drew.
 *
 * @method static ?TailwindSetupObject execute(PromptRunId $promptRunId)
 */
class GetTailwindSetupAction extends Command
{
	public function __construct(
		public readonly PromptRunId $promptRunId,
	) {}

	public function handle(PromptRunRepositoryContract $promptRunRepository, TailwindSetupRepositoryContract $tailwindSetupRepository): ?TailwindSetupObject
	{
		$directory = $promptRunRepository->getById($this->promptRunId)?->directory;

		return $directory === null ? null : $tailwindSetupRepository->getFor($directory);
	}
}
