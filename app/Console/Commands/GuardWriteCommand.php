<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * The hook Claude Code calls before an agent writes to Atlassian. executeWrite
 * carries every writing operation under one tool name, so the operation it
 * names decides: only the ones in devdock.read_only.agent_operations pass.
 */
class GuardWriteCommand extends Command
{
	protected $signature = 'devdock:guard-write';

	protected $description = 'Let an agent write to Atlassian only through the operations DevDock allows';

	public function handle(): int
	{
		$call = json_decode((string) file_get_contents('php://stdin'), true);
		$operation = is_array($call) ? ($call['tool_input']['name'] ?? null) : null;
		$allowed = (array) config('devdock.read_only.agent_operations');

		$isAllowed = is_string($operation) && in_array($operation, $allowed, true);

		$this->line((string) json_encode([
			'hookSpecificOutput' => [
				'hookEventName' => 'PreToolUse',
				'permissionDecision' => $isAllowed ? 'allow' : 'deny',
				'permissionDecisionReason' => $isAllowed
					? 'DevDock lets the agent do ' . $operation . '.'
					: 'DevDock only lets the agent write through ' . implode(', ', $allowed) . '. Put anything else to David.',
			],
		]));

		return self::SUCCESS;
	}
}
