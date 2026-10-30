<?php

declare(strict_types=1);

namespace Src\Task\Contracts;

use Src\Task\Enums\TaskLinkKindEnum;
use Src\Task\Objects\TaskEntity;
use Src\Task\Objects\TaskEntityCollection;
use Src\Task\Objects\TaskId;

interface TaskRepositoryContract
{
	public function getAll(): TaskEntityCollection;

	public function getByKey(string $key): ?TaskEntity;

	/**
	 * Opens the task behind a board key, so there is something to pin to.
	 */
	public function firstOrCreate(string $key): TaskEntity;

	/**
	 * Pins one tie. It becomes the active one of its kind when nothing else is
	 * yet; a value that was pinned to another task moves over.
	 */
	public function link(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity;

	/**
	 * Makes one of the pinned ties the active one of its kind. The others stay
	 * on the task.
	 */
	public function activate(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity;

	/**
	 * Takes a tie off the task. Was it the active one, another of its kind
	 * takes over so the task never stands without one.
	 */
	public function unlink(TaskId $taskId, TaskLinkKindEnum $kind, string $value): TaskEntity;

	/**
	 * Takes the task off the board, with every tie and agent on it.
	 */
	public function delete(TaskId $taskId): void;

	/**
	 * Hands this task an agent under a key we chose ourselves, and makes it the
	 * active one. The agent that was active stays on the task as history.
	 */
	public function openAgent(TaskId $taskId, string $sessionUuid, string $name, ?string $directory): TaskEntity;

	public function activateAgent(TaskId $taskId, string $sessionUuid): TaskEntity;

	/**
	 * Writes down that a round has run on this key, so the next one resumes it
	 * instead of claiming it again.
	 */
	public function markAgentStarted(string $sessionUuid): void;
}
