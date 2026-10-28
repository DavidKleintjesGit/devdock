<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Objects\PromptRunObject;

interface PromptRunRepositoryContract
{
	public function create(PromptRunObject $promptRunObject): PromptRunEntity;

	public function getById(PromptRunId $promptRunId): ?PromptRunEntity;

	/**
	 * The last prompt that was let loose on this card.
	 */
	public function getLatestForCard(string $card): ?PromptRunEntity;

	public function markFinished(PromptRunId $promptRunId, PromptResultObject $promptResultObject): PromptRunEntity;

	public function markFailed(PromptRunId $promptRunId, string $message): PromptRunEntity;
}
