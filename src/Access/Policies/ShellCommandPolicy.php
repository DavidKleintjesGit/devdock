<?php

declare(strict_types=1);

namespace Src\Access\Policies;

use Src\Access\Enums\ShellCommandEnum;
use Src\Access\Objects\ShellCommandDecisionObject;
use Src\Access\Objects\ShellCommandObject;

/**
 * The rule for a shell command an agent, lens or helper is about to run: git
 * is read only. Every git command outside the reading ones is refused,
 * wherever it stands in the line, so committing, pushing and rebasing stay
 * with the buttons. Anything else passes.
 */
class ShellCommandPolicy
{
	/**
	 * Git commands that only read.
	 */
	public const READING = [
		'blame',
		'cat-file',
		'check-attr',
		'check-ignore',
		'describe',
		'diff',
		'for-each-ref',
		'grep',
		'help',
		'log',
		'ls-files',
		'ls-tree',
		'merge-base',
		'name-rev',
		'rev-list',
		'rev-parse',
		'shortlog',
		'show',
		'show-ref',
		'status',
		'version',
	];

	/**
	 * Git commands that read in some forms and write in others.
	 */
	public const LISTING = ['branch', 'config', 'remote', 'stash', 'tag', 'worktree'];

	/**
	 * Shells a command can be handed to as a string.
	 */
	private const SHELLS = ['bash', 'cmd', 'powershell', 'pwsh', 'sh', 'zsh'];

	/**
	 * Options in front of the git command that take the next word as their value.
	 */
	private const OPTIONS_WITH_VALUE = ['-C', '-c', '--git-dir', '--work-tree', '--namespace', '--exec-path', '--config-env'];

	/**
	 * Options that turn listing branches or tags into changing them.
	 */
	private const WRITING_OPTIONS = [
		'branch' => ['-c', '-C', '-d', '-D', '-f', '-m', '-M', '-t', '-u', '--copy', '--delete', '--edit-description', '--force', '--move', '--set-upstream-to', '--track', '--unset-upstream'],
		'tag' => ['-a', '-d', '-f', '-m', '-s', '-u', '--annotate', '--delete', '--force', '--local-user', '--sign'],
	];

	public function decide(ShellCommandObject $shellCommandObject): ShellCommandDecisionObject
	{
		foreach ($this->segments($shellCommandObject->command) as $segment) {
			$gitCommand = $this->writingGitCommandIn($segment);

			if ($gitCommand !== null) {
				return new ShellCommandDecisionObject(ShellCommandEnum::WRITES_GIT, $gitCommand);
			}
		}

		return new ShellCommandDecisionObject(ShellCommandEnum::ALLOWED, null);
	}

	/**
	 * The separate commands in one line: split on the shell's separators and
	 * on the openings of a subshell.
	 *
	 * @return list<string>
	 */
	private function segments(string $command): array
	{
		return array_values(array_filter(
			array_map('trim', preg_split('/&&|\|\||[;|&\n`()]|\$\(/', $command) ?: []),
			fn (string $segment): bool => $segment !== '',
		));
	}

	/**
	 * The git command in this part of the line that writes, or null when it
	 * holds none.
	 */
	private function writingGitCommandIn(string $segment): ?string
	{
		preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'([^\']*)\'|(\S+)/', $segment, $found, PREG_SET_ORDER);

		$words = [];

		foreach ($found as $match) {
			$quoted = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
			$word = $quoted !== '' ? $quoted : ($match[3] ?? '');

			// a command handed to another shell is judged as a command of its own
			if ($quoted !== '' && $words !== [] && $this->handsToShell($words)) {
				$gitCommand = $this->decide(new ShellCommandObject(command: $quoted))->gitCommand;

				if ($gitCommand !== null) {
					return $gitCommand;
				}
			}

			$words[] = $word;
		}

		foreach ($words as $position => $word) {
			if ($this->isGit($word)) {
				$gitCommand = $this->writingGitCommand(array_slice($words, $position + 1));

				if ($gitCommand !== null) {
					return $gitCommand;
				}
			}
		}

		return null;
	}

	/**
	 * Whether the next word is a command for another shell, as in sh -c,
	 * powershell -Command, cmd /c or eval.
	 *
	 * @param  list<string>  $wordsBefore
	 */
	private function handsToShell(array $wordsBefore): bool
	{
		$shell = strtolower(pathinfo(str_replace('\\', '/', $wordsBefore[0]), PATHINFO_FILENAME));

		return in_array($shell, ['eval', 'xargs'], true)
			|| (in_array($shell, self::SHELLS, true) && in_array(strtolower(end($wordsBefore)), ['-c', '-command', '/c', '/k'], true));
	}

	private function isGit(string $word): bool
	{
		$name = strtolower(basename(str_replace('\\', '/', $word)));

		return $name === 'git' || $name === 'git.exe';
	}

	/**
	 * The git command these words run when it writes, or null when it only reads.
	 *
	 * @param  list<string>  $arguments  what follows the word git
	 */
	private function writingGitCommand(array $arguments): ?string
	{
		while ($arguments !== [] && str_starts_with($arguments[0], '-')) {
			$option = array_shift($arguments);

			if (in_array($option, self::OPTIONS_WITH_VALUE, true)) {
				array_shift($arguments);
			}
		}

		$subcommand = array_shift($arguments);

		if ($subcommand === null || in_array($subcommand, self::READING, true)) {
			return null;
		}

		if (in_array($subcommand, self::LISTING, true) && $this->onlyLists($subcommand, $arguments)) {
			return null;
		}

		return $subcommand;
	}

	/**
	 * Whether these arguments use the reading form of a command that can also write.
	 *
	 * @param  list<string>  $arguments
	 */
	private function onlyLists(string $subcommand, array $arguments): bool
	{
		$first = $arguments[0] ?? null;
		$bareWords = array_values(array_filter($arguments, fn (string $argument): bool => ! str_starts_with($argument, '-')));

		$writingOptions = array_filter(
			$arguments,
			fn (string $argument): bool => in_array(explode('=', $argument)[0], self::WRITING_OPTIONS[$subcommand] ?? [], true),
		);

		return match ($subcommand) {
			'branch', 'tag' => (in_array($first, ['-l', '--list'], true) || $bareWords === []) && $writingOptions === [],
			'stash', 'worktree' => in_array($first, $subcommand === 'stash' ? ['list', 'show'] : ['list'], true),
			'remote' => $bareWords === [] || in_array($first, ['get-url', 'show'], true),
			'config' => in_array($first, ['get', 'list'], true)
				|| array_intersect($arguments, ['--get', '--get-all', '--get-regexp', '--list', '-l']) !== [],
		};
	}
}
