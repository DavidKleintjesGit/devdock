<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Src\Packages\Process\DetachedCommand;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Objects\PromptResultObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;

class ClaudeProcessRepository implements ClaudeProcessRepositoryContract
{
	/**
	 * A question the page waits for cannot take as long as a round of work.
	 */
	private const ASK_TIMEOUT = 180;

	public function isAvailable(): bool
	{
		return $this->claudeBinary() === 'claude' || is_file($this->claudeBinary());
	}

	public function startDetached(PromptRunId $promptRunId): void
	{
		resolve(DetachedCommand::class)->artisan(
			sprintf('devdock:prompt --run=%d', (int) $promptRunId->id),
		);
	}

	/**
	 * Opens Claude Code in a terminal window of its own. The window stays open
	 * after Claude stops, so an error is still readable.
	 */
	public function openInTerminal(string $directory, ?string $sessionUuid, array $alsoReads = []): void
	{
		$claude = escapeshellarg($this->claudeBinary());
		$resume = $sessionUuid === null ? '' : ' --resume ' . escapeshellarg($sessionUuid);

		foreach ($this->alsoReads($alsoReads) as $argument) {
			$claude .= ' ' . escapeshellarg($argument);
		}

		// start opens the window and /d puts it in the right folder, so the
		// command itself needs no cd and no second level of quotes
		$command = PHP_OS_FAMILY === 'Windows'
			? sprintf('start "Claude" /d %s cmd /k %s%s', escapeshellarg($directory), $claude, $resume)
			: sprintf('cd %s && %s%s', escapeshellarg($directory), $claude, $resume);

		$handle = popen(
			PHP_OS_FAMILY === 'Windows' ? $command : resolve(DetachedCommand::class)->cutLoose($command),
			'r',
		);

		if (is_resource($handle)) {
			pclose($handle);
		}
	}

	public function stop(PromptRunId $promptRunId): bool
	{
		// the round runs detached, so it is found by what it was started with;
		// killing the tree takes Claude Code down with it
		$needle = 'devdock:prompt --run=' . (int) $promptRunId->id;

		$result = PHP_OS_FAMILY === 'Windows'
			? Process::timeout(30)->run([
				'powershell',
				'-NoProfile',
				'-Command',
				'Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like "*' . $needle . '*" } '
. '| ForEach-Object { taskkill /PID $_.ProcessId /T /F }',
			])
			: Process::timeout(30)->run(['pkill', '-f', $needle]);

		return $result->successful();
	}

	/**
	 * Runs Claude Code in the project folder, without a screen. The json output
	 * carries the session id next to the answer, so a fresh session can be tied
	 * to the board and renamed afterwards.
	 */
	public function run(PromptRunEntity $promptRunEntity, array $alsoReads = []): PromptResultObject
	{
		$directory = $promptRunEntity->directory ?? base_path();

		$arguments = [...$this->alsoReads($alsoReads), '-p', $promptRunEntity->prompt, '--output-format', 'json'];

		if (filled($promptRunEntity->sessionUuid)) {
			// the key is ours: the first round claims it, every one after resumes it
			$arguments = $promptRunEntity->claimsSession
				? ['--session-id', $promptRunEntity->sessionUuid, ...$arguments]
				: ['--resume', $promptRunEntity->sessionUuid, ...$arguments];
		}

		$result = Process::path($directory)
			->env($this->environment())
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
	 * Lets Claude work while it says what it is doing. Every event goes to the
	 * handler and, when a path is given, to a file as it came in, so a round
	 * that fell over can still be read back afterwards.
	 *
	 * @param  Closure(array<string, mixed>): void  $onEvent
	 */
	public function watch(
		string $directory,
		string $question,
		?string $logPath,
		Closure $onEvent,
		array $alsoReads = [],
	): ?string {
		$answer = null;
		$rest = '';

		if ($logPath !== null) {
			File::ensureDirectoryExists(dirname($logPath));
		}

		Process::path(is_dir($directory) ? $directory : base_path())
			->env($this->environment())
			->timeout((int) config('devdock.review.lens_timeout'))
			->idleTimeout((int) config('devdock.review.lens_idle'))
			->run(
				[
					$this->claudeBinary(),
					...$this->alsoReads($alsoReads),
					'-p',
					$question,
					'--output-format',
					'stream-json',
					'--verbose',
				],
				function (string $type, string $output) use (&$answer, &$rest, $logPath, $onEvent): void {
					$rest .= $output;

					// a chunk breaks wherever it breaks, so only whole lines are read
					while (($break = strpos($rest, "\n")) !== false) {
						$line = trim(substr($rest, 0, $break));
						$rest = substr($rest, $break + 1);

						if ($line === '') {
							continue;
						}

						if ($logPath !== null) {
							File::append($logPath, $line . PHP_EOL);
						}

						$event = json_decode($line, true);

						if (! is_array($event)) {
							continue;
						}

						// the closing event carries the answer, whatever else is in it
						if (array_key_exists('result', $event)) {
							$answer = ($event['is_error'] ?? false) === true ? null : (string) $event['result'];
						}

						$onEvent($event);
					}
				},
			);

		return $answer;
	}

	public function ask(string $directory, ?string $sessionUuid, string $question): ?string
	{
		$arguments = ['-p', $question, '--output-format', 'json'];

		if (filled($sessionUuid)) {
			$arguments = ['--resume', $sessionUuid, ...$arguments];
		}

		$result = Process::path(is_dir($directory) ? $directory : base_path())
			->env($this->environment())
			->timeout(self::ASK_TIMEOUT)
			->run([$this->claudeBinary(), ...$arguments]);

		$answer = json_decode($result->output(), true);

		return is_array($answer) && ($answer['is_error'] ?? false) === false
			? ($answer['result'] ?? null)
			: null;
	}

	/**
	 * The long-lived token, handed to the process rather than set on the
	 * machine. Without one, Claude Code falls back on the session you are
	 * signed in with, which expires.
	 *
	 * @return array<string, string>
	 */
	/**
	 * The folders outside the working copy this session may open. Nothing is
	 * added by itself, so a session stays where it was put.
	 *
	 * @param  array<int, string>  $alsoReads
	 * @return array<int, string>
	 */
	private function alsoReads(array $alsoReads): array
	{
		$arguments = [];

		foreach ($alsoReads as $directory) {
			if (is_dir($directory)) {
				$arguments[] = '--add-dir';
				$arguments[] = $directory;
			}
		}

		return $arguments;
	}

	private function environment(): array
	{
		$token = (string) config('devdock.claude_token');

		return $token === '' ? [] : ['CLAUDE_CODE_OAUTH_TOKEN' => $token];
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
}
