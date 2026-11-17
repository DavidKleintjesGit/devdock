<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Enums\WorkingCopyTaskEnum;

/**
 * A task on the working copy of a card, with what it needs to run in a
 * process of its own.
 */
class WorkingCopyTaskObject extends ValueObject
{
	/**
	 * @param  list<string>  $files  the files a commit or a commit message is about
	 */
	public function __construct(
		public readonly string $card,
		public readonly WorkingCopyTaskEnum $task,
		public readonly string $project,
		public readonly string $branch,
		public readonly ?DatabaseFillEnum $fill = null,
		public readonly bool $carriesChanges = false,
		public readonly ?string $path = null,
		public readonly ?string $ticketKey = null,
		public readonly array $files = [],
		public readonly ?string $message = null,
		public readonly ?string $body = null,
		public readonly bool $pushesAfter = false,
		public readonly ?string $prompt = null,
		public readonly bool $restartsLoop = false,
		public readonly ?string $target = null,
	) {
		parent::__construct();
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	public static function from(array $data): self
	{
		return new self(
			card: (string) $data['card'],
			task: WorkingCopyTaskEnum::from((string) $data['task']),
			project: (string) $data['project'],
			branch: (string) $data['branch'],
			fill: DatabaseFillEnum::tryFrom((string) ($data['fill'] ?? '')),
			carriesChanges: (bool) ($data['carries_changes'] ?? false),
			path: $data['path'] ?? null,
			ticketKey: $data['ticket_key'] ?? null,
			files: array_values((array) ($data['files'] ?? [])),
			message: $data['message'] ?? null,
			body: $data['body'] ?? null,
			pushesAfter: (bool) ($data['pushes_after'] ?? false),
			prompt: $data['prompt'] ?? null,
			restartsLoop: (bool) ($data['restarts_loop'] ?? false),
			target: $data['target'] ?? null,
		);
	}

	/**
	 * The task as the command line carries it to the process that runs it.
	 */
	public function toArgument(): string
	{
		return base64_encode((string) json_encode([
			'card' => $this->card,
			'task' => $this->task->value,
			'project' => $this->project,
			'branch' => $this->branch,
			'fill' => $this->fill?->value,
			'carries_changes' => $this->carriesChanges,
			'path' => $this->path,
			'ticket_key' => $this->ticketKey,
			'files' => $this->files,
			'message' => $this->message,
			'body' => $this->body,
			'pushes_after' => $this->pushesAfter,
			'prompt' => $this->prompt,
			'restarts_loop' => $this->restartsLoop,
			'target' => $this->target,
		]));
	}

	public static function fromArgument(string $argument): self
	{
		return self::from((array) json_decode((string) base64_decode($argument), true));
	}
}
