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
}
