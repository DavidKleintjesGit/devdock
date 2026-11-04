<?php

declare(strict_types=1);

namespace Src\Access\Actions;

use Src\Access\Enums\AgentRoleEnum;
use Src\Access\Objects\RoleAccessObject;
use Src\Access\Policies\ShellCommandPolicy;
use Src\Packages\Cqrs\Command;

/**
 * What a role may reach, from devdock.access. Every writing tool on the
 * Atlassian and Docs servers is denied; an agent keeps executeWrite for the
 * operations it may still carry out, behind the write guard. A role that does
 * not change files is denied the tools that do.
 *
 * @method static RoleAccessObject execute(AgentRoleEnum $agentRole)
 */
class GetRoleAccessAction extends Command
{
	private const EDITING_TOOLS = ['Edit', 'Write', 'NotebookEdit'];

	public function __construct(
		public readonly AgentRoleEnum $agentRole,
	) {}

	public function handle(): RoleAccessObject
	{
		$writableOperations = $this->agentRole === AgentRoleEnum::AGENT
			? array_values((array) config('devdock.access.agent_operations'))
			: [];

		$writingTools = array_diff(
			(array) config('devdock.access.writing_tools'),
			$writableOperations === [] ? [] : ['executeWrite'],
		);

		$deniedTools = collect((array) config('devdock.access.servers'))
			->crossJoin($writingTools)
			->map(fn (array $pair): string => 'mcp__' . $pair[0] . '__' . $pair[1])
			->concat($this->agentRole->editsFiles() ? [] : self::EDITING_TOOLS)
			->values()
			->all();

		return new RoleAccessObject(
			agentRole: $this->agentRole,
			deniedTools: $deniedTools,
			writableOperations: $writableOperations,
			gitReading: [...ShellCommandPolicy::READING, ...ShellCommandPolicy::LISTING],
		);
	}
}
