<?php

declare(strict_types=1);

namespace Src\Access\Actions;

use Src\Access\Enums\AgentRoleEnum;
use Src\Access\Objects\AccessOverviewObject;
use Src\Access\Objects\ShellCommandObject;
use Src\Access\Policies\ShellCommandPolicy;
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
		$roleAccesses = array_map(
			fn (AgentRoleEnum $agentRole) => GetRoleAccessAction::execute($agentRole),
			AgentRoleEnum::cases(),
		);

		return new AccessOverviewObject(
			roleAccesses: $roleAccesses,
			servers: array_values((array) config('devdock.access.servers')),
			writingTools: array_values((array) config('devdock.access.writing_tools')),
			agentOperations: GetRoleAccessAction::execute(AgentRoleEnum::AGENT)->writableOperations,
			gitReading: ShellCommandPolicy::READING,
			gitListing: ShellCommandPolicy::LISTING,
			gitRefusal: (string) resolve(ShellCommandPolicy::class)->decide(new ShellCommandObject(command: 'git commit'))->reason(),
		);
	}
}
