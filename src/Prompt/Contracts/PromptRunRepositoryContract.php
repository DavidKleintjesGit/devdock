<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunEntityCollection;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Objects\PromptRunObject;

interface PromptRunRepositoryContract
{
	public function create(PromptRunObject $promptRunObject): PromptRunEntity;

	public function getById(PromptRunId $promptRunId): ?PromptRunEntity;

	/**
	 * Every round of this card, oldest first: the conversation with its agent.
	 */
	public function getAllForCard(string $card): PromptRunEntityCollection;

	/**
	 * The last prompt that was let loose on this card.
	 */
	public function getLatestForCard(string $card): ?PromptRunEntity;

	/**
	 * The last turn of every card, keyed by card.
	 *
	 * @return array<string, PromptRunEntity>
	 */
	public function getLatestPerCard(): array;

	/**
	 * De beurten die de agent op een ronde deed, op rondenummer. Daarmee is
	 * per ronde terug te lezen wat hij met de punten deed.
	 *
	 * @return array<int, PromptRunEntity>
	 */
	public function getByReviewRounds(string $card): array;

	public function markFinished(PromptRunId $promptRunId, PromptResultObject $promptResultObject): PromptRunEntity;

	public function markFailed(PromptRunId $promptRunId, string $message): PromptRunEntity;

	/**
	 * Where the events of a round are written as they come in, so the page can
	 * follow the round while it works.
	 */
	public function logPath(PromptRunId $promptRunId): string;

	public function countLogsForCard(string $card): int;

	/**
	 * Takes the event logs of every round on this card off disk; gives back
	 * how many there were.
	 */
	public function deleteLogsForCard(string $card): int;
}
