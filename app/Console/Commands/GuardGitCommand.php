<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Access\Enums\GitGrantEnum;
use Src\Access\Objects\ShellCommandObject;
use Src\Access\Policies\ShellCommandPolicy;

/**
 * The hook Claude Code calls before an agent, lens or helper runs a shell
 * command. A command that writes to git is denied with the reason; anything
 * else is left to Claude Code's own permissions.
 */
class GuardGitCommand extends Command
{
	protected $signature = 'devdock:guard-git {--grant=* : Git work handed over for this turn, such as rebase}';

	protected $description = 'Let an agent only read git; writing is done with the buttons';

	public function handle(): int
	{
		$call = json_decode((string) file_get_contents('php://stdin'), true);
		$command = is_array($call) ? ($call['tool_input']['command'] ?? null) : null;

		$refusal = is_string($command) ? resolve(ShellCommandPolicy::class)->decide(new ShellCommandObject(command: $command, grants: $this->grants()))->reason() : null;

		if ($refusal !== null) {
			$this->line((string) json_encode([
				'hookSpecificOutput' => [
					'hookEventName' => 'PreToolUse',
					'permissionDecision' => 'deny',
					'permissionDecisionReason' => $refusal,
				],
			]));
		}

		return self::SUCCESS;
	}

	/**
	 * @return list<GitGrantEnum>
	 */
	private function grants(): array
	{
		return array_values(array_filter(array_map(
			fn (string $grant): ?GitGrantEnum => GitGrantEnum::tryFrom($grant),
			(array) $this->option('grant'),
		)));
	}
}
