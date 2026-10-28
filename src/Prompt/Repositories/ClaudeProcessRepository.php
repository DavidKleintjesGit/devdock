<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;

class ClaudeProcessRepository implements ClaudeProcessRepositoryContract
{
	public function isAvailable(): bool
	{
		return $this->claudeBinary() === 'claude' || is_file($this->claudeBinary());
	}

	public function startDetached(PromptRunId $promptRunId): void
	{
		$command = sprintf(
			'%s %s devdock:prompt --run=%d',
			escapeshellarg($this->phpBinary()),
			escapeshellarg(base_path('artisan')),
			(int) $promptRunId->id,
		);

		$handle = popen($this->detached($command), 'r');

		if (is_resource($handle)) {
			pclose($handle);
		}
	}

	/**
	 * Runs Claude Code in the project folder, without a screen. The json output
	 * carries the session id next to the answer, so a fresh session can be tied
	 * to the board and renamed afterwards.
	 */
	public function run(PromptRunEntity $promptRunEntity): PromptResultObject
	{
		$directory = $promptRunEntity->project === null
			? base_path()
			: config('devdock.paths.sites') . '/' . $promptRunEntity->project;

		$arguments = ['-p', $promptRunEntity->prompt, '--output-format', 'json'];

		if (filled($promptRunEntity->sessionUuid)) {
			$arguments = ['--resume', $promptRunEntity->sessionUuid, ...$arguments];
		}

		$result = Process::path($directory)
			->timeout((int) config('devdock.prompt_timeout'))
			->run([$this->claudeBinary(), ...$arguments]);

		$answer = json_decode($result->output(), true);

		// Claude Code also exits with an error code when it answers politely that
		// something is wrong, and that explanation is more useful than the raw
		// output
		if (is_array($answer)) {
			return PromptResultObject::from($answer);
		}

		throw new RuntimeException(
			trim($result->errorOutput() ?: $result->output()) ?: __('Claude Code gaf geen antwoord.'),
		);
	}

	/**
	 * The path to Claude Code. With nothing in the settings, it looks next to
	 * the sessions folder, where the Claude Code install itself sits.
	 */
	private function claudeBinary(): string
	{
		if (filled(config('devdock.claude_binary'))) {
			return (string) config('devdock.claude_binary');
		}

		$name = PHP_OS_FAMILY === 'Windows' ? 'claude.exe' : 'claude';
		$home = dirname((string) config('devdock.paths.sessions'), 2);

		foreach ([$home . '/.local/bin/' . $name, $home . '/.claude/local/' . $name] as $candidate) {
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		return 'claude';
	}

	private function phpBinary(): string
	{
		if (filled(config('devdock.php_binary'))) {
			return (string) config('devdock.php_binary');
		}

		if (PHP_SAPI === 'cli') {
			return PHP_BINARY;
		}

		$name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
		$sibling = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $name;

		return is_file($sibling) ? $sibling : 'php';
	}

	private function detached(string $command): string
	{
		return PHP_OS_FAMILY === 'Windows'
			? 'start /B "" ' . $command
			: $command . ' > /dev/null 2>&1 &';
	}
}
