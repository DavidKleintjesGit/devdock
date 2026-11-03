<?php

declare(strict_types=1);

namespace Src\Access\Actions;

use Src\Access\Enums\AgentRoleEnum;
use Src\Access\Objects\AccessOverviewObject;
use Src\Packages\Cqrs\Command;

/**
 * What every role may reach, read from the same sources DevDock hands to
 * Claude Code at every start.
 *
 * @method static AccessOverviewObject execute()
 */
class GetAccessOverviewAction extends Command
{
	public function handle(): AccessOverviewObject
	{
		$accessPolicies = array_map(
			fn (AgentRoleEnum $agentRole) => GetAccessPolicyAction::execute($agentRole),
			AgentRoleEnum::cases(),
		);

		return new AccessOverviewObject(
			accessPolicies: $accessPolicies,
			servers: array_values((array) config('devdock.access.servers')),
			writingTools: array_values((array) config('devdock.access.writing_tools')),
			agentOperations: GetAccessPolicyAction::execute(AgentRoleEnum::AGENT)->writableOperations,
			gitReading: JudgeShellCommandAction::READING,
			gitListing: JudgeShellCommandAction::LISTING,
			gitRefusal: (string) JudgeShellCommandAction::execute('git commit'),
		);
	}
}
