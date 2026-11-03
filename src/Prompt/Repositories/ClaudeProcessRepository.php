<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Closure;
use Dotenv\Dotenv;
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
	public function openInTerminal(string $directory, ?string $sessionUuid, array $alsoReads = [], ?string $instructionsFile = null): void
	{
		$claude = escapeshellarg($this->claudeBinary());
		$resume = $sessionUuid === null ? '' : ' --resume ' . escapeshellarg($sessionUuid);

		foreach ($this->alsoReads($alsoReads) as $argument) {
			$claude .= ' ' . escapeshellarg($argument);
		}

		if ($instructionsFile !== null && is_file($instructionsFile)) {
			$claude .= ' --append-system-prompt-file ' . escapeshellarg($instructionsFile);
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
	 * Runs Claude Code in the project folder, without a screen. Every event is
	 * written to the log as it comes in, text included while it is being
	 * typed, so the page can follow along. The closing event carries the
	 * session id next to the answer, so a fresh session can be tied to the
	 * board and renamed afterwards.
	 */
	public function run(PromptRunEntity $promptRunEntity, array $alsoReads, string $logPath, string $instructionsPath): PromptResultObject
	{
		$directory = $promptRunEntity->directory ?? base_path();

		// the prompt goes in through stdin: on Windows the command line passes cmd.exe,
		// which cuts it off past 8191 characters, and a long prompt then arrives empty
		$arguments = [
			...$this->alsoReads($alsoReads),
			...$this->readOnly(['executeWrite']),
			'--settings',
			$this->writeGuardSettings(),
			'-p',
			'--output-format',
			'stream-json',
			'--verbose',
			'--include-partial-messages',
			'--append-system-prompt-file',
			$instructionsPath,
		];

		if (filled($promptRunEntity->sessionUuid)) {
			// the key is ours: the first round claims it, every one after resumes it
			$arguments = $promptRunEntity->claimsSession
				? ['--session-id', $promptRunEntity->sessionUuid, ...$arguments]
				: ['--resume', $promptRunEntity->sessionUuid, ...$arguments];
		}

		File::ensureDirectoryExists(dirname($logPath));
		File::put($logPath, '');

		$answer = null;
		$rest = '';

		$result = Process::path($directory)
			->env($this->environment())
			->input($promptRunEntity->prompt)
			->timeout((int) config('devdock.prompt_timeout'))
			->run(
				[$this->claudeBinary(), ...$arguments],
				function (string $type, string $output) use (&$answer, &$rest, $logPath): void {
					if ($type !== 'out') {
						return;
					}

					$rest .= $output;

					// a chunk breaks wherever it breaks, so only whole lines are written
					while (($break = strpos($rest, "\n")) !== false) {
						$line = trim(substr($rest, 0, $break));
						$rest = substr($rest, $break + 1);

						if ($line === '') {
							continue;
						}

						File::append($logPath, $line . PHP_EOL);

						$event = json_decode($line, true);

						if (is_array($event) && ($event['type'] ?? null) === 'result') {
							$answer = $event;
						}
					}
				},
			);

		$this->compact($logPath);

		// Claude Code also exits with an error code when it answers politely that
		// something is wrong, and that explanation is more useful than the raw
		// output
		if (is_array($answer)) {
			return PromptResultObject::from($answer);
		}

		throw new RuntimeException(
			trim($result->errorOutput() ?: $rest) ?: __('Claude Code gaf geen antwoord.'),
		);
	}

	/**
	 * Drops the typing from a finished log: the whole messages are in it as
	 * well, and the deltas make up most of its size.
	 */
	private function compact(string $logPath): void
	{
		$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

		File::put($logPath, implode(PHP_EOL, array_filter(
			$lines,
			fn (string $line) => ! str_starts_with($line, '{"type":"stream_event"'),
		)) . PHP_EOL);
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

		// the lenses of a round start together and share this folder; whoever is second finds it made
		if ($logPath !== null && ! is_dir(dirname($logPath))) {
			@mkdir(dirname($logPath), 0755, true);
		}

		Process::path(is_dir($directory) ? $directory : base_path())
			->env($this->environment())
			// through stdin for the same reason as in run()
			->input($question)
			->timeout((int) config('devdock.review.lens_timeout'))
			->idleTimeout((int) config('devdock.review.lens_idle'))
			->run(
				[
					$this->claudeBinary(),
					...$this->alsoReads($alsoReads),
					...$this->readOnly(),
					'-p',
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
		$arguments = ['-p', '--output-format', 'json'];

		if (filled($sessionUuid)) {
			$arguments = ['--resume', $sessionUuid, ...$arguments];
		}

		$result = Process::path(is_dir($directory) ? $directory : base_path())
			->env($this->environment())
			// through stdin for the same reason as in run()
			->input($question)
			->timeout(self::ASK_TIMEOUT)
			->run([$this->claudeBinary(), ...$arguments]);

		$answer = json_decode($result->output(), true);

		return is_array($answer) && ($answer['is_error'] ?? false) === false
			? ($answer['result'] ?? null)
			: null;
	}

	/**
	 * Denies every tool that writes to Jira, Confluence, Bitbucket or Claude
	 * Docs, on every server in devdock.read_only, except the tools that stay
	 * open behind the write guard; reading stays open.
	 *
	 * @param  array<int, string>  $guardedTools
	 * @return array<int, string>
	 */
	private function readOnly(array $guardedTools = []): array
	{
		$deniedTools = collect((array) config('devdock.read_only.servers'))
			->crossJoin(array_diff((array) config('devdock.read_only.writing_tools'), $guardedTools))
			->map(fn (array $pair) => 'mcp__' . $pair[0] . '__' . $pair[1])
			->implode(',');

		return $deniedTools === '' ? [] : ['--disallowedTools', $deniedTools];
	}

	/**
	 * The settings file that puts devdock:guard-write in front of every
	 * executeWrite of an agent, as the path Claude Code reads it from.
	 */
	private function writeGuardSettings(): string
	{
		$path = storage_path('app/claude/agent-settings.json');
		$command = sprintf(
			'"%s" "%s" devdock:guard-write',
			str_replace('\\', '/', PHP_BINARY),
			str_replace('\\', '/', base_path('artisan')),
		);

		File::ensureDirectoryExists(dirname($path));
		File::put($path, (string) json_encode([
			'hooks' => [
				'PreToolUse' => [[
					'matcher' => 'mcp__.*__executeWrite',
					'hooks' => [['type' => 'command', 'command' => $command, 'timeout' => 30]],
				]],
			],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		return $path;
	}

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

	/**
	 * What Claude starts with: DevDock's own .env settings taken out, and the
	 * long-lived token put in. Without a token, Claude Code falls back on the
	 * session you are signed in with, which expires.
	 */
	private function environment(): array
	{
		$token = (string) config('devdock.claude_token');

		// Laravel writes DevDock's .env into the process environment, and a project
		// run from here would read DB_CONNECTION and APP_ENV as its own; false unsets them
		$ownSettings = array_fill_keys(array_keys(Dotenv::parse((string) @file_get_contents(base_path('.env')))), false);

		return $token === '' ? $ownSettings : [...$ownSettings, 'CLAUDE_CODE_OAUTH_TOKEN' => $token];
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
