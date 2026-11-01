<?php

declare(strict_types=1);

namespace Src\Prompt\Contracts;

use Closure;
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
	 * Opens Claude Code in a terminal of its own, in that folder and on that
	 * session, so you can carry on by hand where the board left off.
	 */
	public function openInTerminal(string $directory, ?string $sessionUuid, array $alsoReads = []): void;

	/**
	 * Breaks off the round that is working on this row. The process tree goes
	 * with it, so Claude Code stops too.
	 */
	public function stop(PromptRunId $promptRunId): bool;

	/**
	 * Carries out the prompt and waits for the answer, writing every event to
	 * the log on the way. The instructions go along as an addition to the
	 * system prompt, so they hold for every round and not only a built one.
	 *
	 * @param  array<int, string>  $alsoReads
	 */
	public function run(PromptRunEntity $promptRunEntity, array $alsoReads, string $logPath, string $instructions): PromptResultObject;

	/**
	 * Asks one short question and waits for the answer. Meant for something
	 * the page needs straight away, not for work that takes minutes.
	 */
	public function ask(string $directory, ?string $sessionUuid, string $question): ?string;

	/**
	 * Lets Claude work while it says what it is doing. Meant for work that
	 * takes minutes and that somebody is watching.
	 *
	 * @param  Closure(array<string, mixed>): void  $onEvent
	 */
	public function watch(
		string $directory,
		string $question,
		?string $logPath,
		Closure $onEvent,
		array $alsoReads = [],
	): ?string;
}
