<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;

interface ClaudeProcessRepositoryContract
{
	/**
	 * Whether Claude Code can be found on this machine.
	 */
	public function isAvailable(): bool;

	/**
	 * Puts away the command that carries out this prompt, as a separate process.
	 * Claude Code can be busy for minutes, so the web does not wait on it.
	 */
	public function startDetached(PromptRunId $promptRunId): void;

	/**
	 * Carries out the prompt and waits for the answer.
	 */
	public function run(PromptRunEntity $promptRunEntity): PromptResultObject;
}
