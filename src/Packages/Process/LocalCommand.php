<?php

declare(strict_types=1);

namespace Src\Packages\Process;

use RuntimeException;

/**
 * A short command that only reads the disk, such as git status or git diff,
 * started straight away. On Windows the Process facade goes through cmd.exe
 * and temp files, which costs some 170 ms per call; a page that asks git a
 * dozen things pays that a dozen times.
 *
 * It has no timeout, so it is only for commands that finish on their own:
 * nothing that talks to a remote or waits for a person.
 */
class LocalCommand
{
	/**
	 * @param  array<int, string>  $command
	 */
	public static function run(string $path, array $command, ?string $input = null): LocalCommandResult
	{
		// a file for what goes in and for the errors, so neither pipe can fill up and stall the other
		$inputFile = tmpfile();
		$errorFile = tmpfile();

		if ($inputFile === false || $errorFile === false) {
			throw new RuntimeException('No temporary file for ' . $command[0] . '.');
		}

		fwrite($inputFile, (string) $input);
		rewind($inputFile);

		$process = proc_open(
			$command,
			[0 => $inputFile, 1 => ['pipe', 'w'], 2 => $errorFile],
			$pipes,
			$path,
			// a read never takes git's index lock, so it never stands in the way of an agent at work
			[...getenv(), 'GIT_OPTIONAL_LOCKS' => '0', 'GIT_TERMINAL_PROMPT' => '0'],
			['bypass_shell' => true],
		);

		if (! is_resource($process)) {
			return new LocalCommandResult($command, -1, '', 'Could not start ' . $command[0] . '.');
		}

		$output = (string) stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$exitCode = proc_close($process);

		rewind($errorFile);
		$errorOutput = (string) stream_get_contents($errorFile);

		return new LocalCommandResult($command, $exitCode, $output, $errorOutput);
	}

	/**
	 * Several short reads started at once and waited for together, each in its
	 * own folder. The results keep the keys the commands came in with.
	 *
	 * @param  array<string, array{0: string, 1: array<int, string>}>  $commands  per key the folder and the command
	 * @return array<string, LocalCommandResult>
	 */
	public static function pool(array $commands): array
	{
		$environment = [...getenv(), 'GIT_OPTIONAL_LOCKS' => '0', 'GIT_TERMINAL_PROMPT' => '0'];
		$started = [];
		$results = [];

		foreach ($commands as $key => [$path, $command]) {
			// files instead of pipes: nothing has to be read while the others still run
			$outputFile = tmpfile();
			$errorFile = tmpfile();

			if ($outputFile === false || $errorFile === false) {
				throw new RuntimeException('No temporary file for ' . $command[0] . '.');
			}

			$process = proc_open($command, [0 => ['pipe', 'r'], 1 => $outputFile, 2 => $errorFile], $pipes, $path, $environment, ['bypass_shell' => true]);

			if (! is_resource($process)) {
				$results[$key] = new LocalCommandResult($command, -1, '', 'Could not start ' . $command[0] . '.');

				continue;
			}

			fclose($pipes[0]);
			$started[$key] = [$command, $process, $outputFile, $errorFile];
		}

		foreach ($started as $key => [$command, $process, $outputFile, $errorFile]) {
			$exitCode = proc_close($process);

			rewind($outputFile);
			rewind($errorFile);

			$results[$key] = new LocalCommandResult($command, $exitCode, (string) stream_get_contents($outputFile), (string) stream_get_contents($errorFile));
		}

		return array_replace(array_intersect_key($commands, $results), $results);
	}
}
