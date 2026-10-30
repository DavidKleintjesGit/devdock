<?php

declare(strict_types=1);

namespace Src\Task\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Task\Enums\TaskLinkKindEnum;

/**
 * A task that someone tied together by hand. What is pinned here beats what
 * the board works out from names, and it survives the next fetch.
 *
 * Several of a kind may hang on a task — a reopened pull request, a second
 * branch — but exactly one of each is active, and that is the one the task
 * works with.
 */
class TaskEntity extends ValueObject
{
	public function __construct(
		public readonly TaskId $taskId,
		public readonly string $key,
		public readonly TaskLinkEntityCollection $links,
		public readonly TaskAgentEntityCollection $agents,
		public readonly ?CarbonImmutable $createdAt,
	) {
		parent::__construct();
	}

	public function ticketKey(): ?string
	{
		return $this->activeFor(TaskLinkKindEnum::TICKET);
	}

	public function project(): ?string
	{
		return $this->activeFor(TaskLinkKindEnum::PROJECT);
	}

	public function branch(): ?string
	{
		return $this->activeFor(TaskLinkKindEnum::BRANCH);
	}

	public function pullRequest(): ?string
	{
		return $this->activeFor(TaskLinkKindEnum::PULL_REQUEST);
	}

	public function activeFor(TaskLinkKindEnum $kind): ?string
	{
		foreach ($this->links as $taskLinkEntity) {
			if ($taskLinkEntity->kind === $kind && $taskLinkEntity->isActive) {
				return $taskLinkEntity->value;
			}
		}

		return null;
	}

	/**
	 * Every value of one kind, active or not.
	 *
	 * @return array<int, string>
	 */
	public function valuesFor(TaskLinkKindEnum $kind): array
	{
		$values = [];

		foreach ($this->links as $taskLinkEntity) {
			if ($taskLinkEntity->kind === $kind) {
				$values[] = $taskLinkEntity->value;
			}
		}

		return $values;
	}

	/**
	 * @return array<int, TaskLinkEntity>
	 */
	public function linksFor(TaskLinkKindEnum $kind): array
	{
		$taskLinkEntities = [];

		foreach ($this->links as $taskLinkEntity) {
			if ($taskLinkEntity->kind === $kind) {
				$taskLinkEntities[] = $taskLinkEntity;
			}
		}

		return $taskLinkEntities;
	}

	public function activeAgent(): ?TaskAgentEntity
	{
		foreach ($this->agents as $taskAgentEntity) {
			if ($taskAgentEntity->isActive) {
				return $taskAgentEntity;
			}
		}

		return null;
	}

	/**
	 * Whether anything at all is pinned, or this is only an empty shell.
	 */
	public function hasLinks(): bool
	{
		return $this->links->count() > 0;
	}

	/**
	 * Once a branch or a pull request hangs on it, the work has started.
	 */
	public function hasStarted(): bool
	{
		return $this->branch() !== null || $this->pullRequest() !== null;
	}
}
