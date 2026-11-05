<?php

declare(strict_types=1);

namespace Src\Access\Policies;

use Src\Access\Enums\GitGrantEnum;
use Src\Access\Enums\ShellCommandEnum;
use Src\Access\Objects\ShellCommandDecisionObject;
use Src\Access\Objects\ShellCommandObject;

/**
 * The rule for a shell command an agent, lens or helper is about to run: git
 * is read only. Every git command outside the reading ones is refused,
 * wherever it stands in the line, so committing, pushing and rebasing stay
 * with the buttons; a grant you gave with a button lets its own commands
 * through for that turn. Anything else passes.
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
	 * Hosts of the services an agent may only read.
	 */
	private const GUARDED_HOSTS = ['atlassian.com', 'atlassian.net', 'bitbucket.org', 'circleci.com', 'github.com'];

	/**
	 * Programs that send a request over the web.
	 */
	private const WEB_CLIENTS = ['curl', 'http', 'invoke-restmethod', 'invoke-webrequest', 'irm', 'iwr', 'wget'];

	/**
	 * Options of those programs that send a body along, which makes the request a
	 * write. Exact case: curl -F sends a form, curl -f only fails on an error.
	 */
	private const SENDING_OPTIONS = ['-d', '-F', '-T', '--data', '--data-ascii', '--data-binary', '--data-raw', '--data-urlencode', '--form', '--form-string', '--json', '--upload-file', '--post-data', '--post-file', '--body-data', '--body-file'];

	/**
	 * Options of the PowerShell clients that send a body along; PowerShell ignores case.
	 */
	private const POWERSHELL_SENDING_OPTIONS = ['-body', '-infile', '-form'];

	/**
	 * Words in a twg command that change something in Jira, Confluence or Bitbucket.
	 */
	private const TWG_WRITING_WORDS = ['add', 'approve', 'assign', 'comment', 'create', 'decline', 'delete', 'edit', 'merge', 'move', 'post', 'publish', 'put', 'remove', 'reopen', 'transition', 'unapprove', 'update', 'upload', 'write'];

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
			$gitCommand = $this->writingGitCommandIn($segment, $shellCommandObject->grants);

			if ($gitCommand !== null) {
				return new ShellCommandDecisionObject(ShellCommandEnum::WRITES_GIT, $gitCommand);
			}

			$remoteCommand = $this->writingRemoteCommandIn($segment);

			if ($remoteCommand !== null) {
				return new ShellCommandDecisionObject(ShellCommandEnum::WRITES_REMOTE, $remoteCommand);
			}
		}

		return new ShellCommandDecisionObject(ShellCommandEnum::ALLOWED, null);
	}

	/**
	 * The program in this part of the line that writes to a guarded service,
	 * or null when it holds none: twg with a writing word, or a web client that
	 * sends something to a guarded host.
	 */
	private function writingRemoteCommandIn(string $segment): ?string
	{
		$words = preg_split('/\s+/', trim(str_replace(['"', "'"], ' ', $segment))) ?: [];
		$program = strtolower(pathinfo(str_replace('\\', '/', (string) ($words[0] ?? '')), PATHINFO_FILENAME));

		// a command handed to another shell is judged as a command of its own
		$handedOver = match (true) {
			in_array($program, ['eval', 'xargs'], true) => array_slice($words, 1),
			count($words) > 2 && $this->handsToShell(array_slice($words, 0, 2)) => array_slice($words, 2),
			default => null,
		};

		if ($handedOver !== null) {
			return $this->writingRemoteCommandIn(implode(' ', $handedOver));
		}

		if ($program === 'twg') {
			$writingWords = array_values(array_intersect(array_map(strtolower(...), $words), self::TWG_WRITING_WORDS));

			return $writingWords === [] ? null : 'twg ' . implode(' ', $writingWords);
		}

		if (! in_array($program, self::WEB_CLIENTS, true) || ! $this->namesGuardedHost($words)) {
			return null;
		}

		return $this->sendsSomething($words) ? $program : null;
	}

	/**
	 * @param  list<string>  $words
	 */
	private function namesGuardedHost(array $words): bool
	{
		foreach ($words as $word) {
			$host = (string) parse_url(str_contains($word, '://') ? $word : 'https://' . $word, PHP_URL_HOST);

			foreach (self::GUARDED_HOSTS as $guardedHost) {
				if ($host === $guardedHost || str_ends_with($host, '.' . $guardedHost)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the request carries a body or asks for a method other than reading.
	 *
	 * @param  list<string>  $words
	 */
	private function sendsSomething(array $words): bool
	{
		foreach ($words as $position => $word) {
			$option = explode('=', $word, 2)[0];

			if (in_array($option, self::SENDING_OPTIONS, true) || in_array(strtolower($option), self::POWERSHELL_SENDING_OPTIONS, true)) {
				return true;
			}

			// curl -X POST, --request=PUT, wget --method=PUT, powershell -Method Delete
			if (in_array($option, ['-X', '--request', '--method'], true) || strtolower($option) === '-method') {
				$method = str_contains($word, '=') ? explode('=', $word, 2)[1] : (string) ($words[$position + 1] ?? '');

				if (! in_array(strtolower($method), ['get', 'head', 'options'], true)) {
					return true;
				}
			}

			// curl -XPOST
			if (preg_match('/^-X(?!GET$|HEAD$)[A-Z]+$/', $word) === 1) {
				return true;
			}
		}

		return false;
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
	/**
	 * @param  list<GitGrantEnum>  $grants
	 */
	private function writingGitCommandIn(string $segment, array $grants): ?string
	{
		preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'([^\']*)\'|(\S+)/', $segment, $found, PREG_SET_ORDER);

		$words = [];

		foreach ($found as $match) {
			$quoted = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
			$word = $quoted !== '' ? $quoted : ($match[3] ?? '');

			// a command handed to another shell is judged as a command of its own
			if ($quoted !== '' && $words !== [] && $this->handsToShell($words)) {
				$shellCommandDecisionObject = $this->decide(new ShellCommandObject(command: $quoted, grants: $grants));

				if ($shellCommandDecisionObject->outcome === ShellCommandEnum::WRITES_GIT) {
					return $shellCommandDecisionObject->refusedCommand;
				}
			}

			$words[] = $word;
		}

		foreach ($words as $position => $word) {
			if ($this->isGit($word)) {
				$gitCommand = $this->writingGitCommand(array_slice($words, $position + 1), $grants);

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
	 * @param  list<GitGrantEnum>  $grants
	 */
	private function writingGitCommand(array $arguments, array $grants): ?string
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

		foreach ($grants as $gitGrant) {
			if (in_array($subcommand, $gitGrant->commands(), true)) {
				return null;
			}
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
