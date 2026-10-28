<?php

declare(strict_types=1);

namespace Src\Prompt\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;

/**
 * @method static ?PromptRunEntity execute(string $card)
 */
class GetLatestPromptRunAction extends Command
{
	public function __construct(
		public readonly string $card,
	) {
	}

	public function handle(PromptRunRepositoryContract $promptRunRepository): ?PromptRunEntity
	{
		return $promptRunRepository->getLatestForCard($this->card);
	}
}
