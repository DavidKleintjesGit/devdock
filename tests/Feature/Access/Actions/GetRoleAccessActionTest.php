<?php

declare(strict_types=1);

namespace Tests\Feature\Access\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Access\Actions\GetRoleAccessAction;
use Src\Access\Enums\AgentRoleEnum;
use Tests\TestCase;

/**
 * @see GetRoleAccessAction
 */
class GetRoleAccessActionTest extends TestCase
{
	#[Test]
	public function execute_denies_every_writing_tool_on_every_listed_server(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['createJiraIssue', 'executeWrite'],
		]);

		$roleAccessObject = GetRoleAccessAction::execute(AgentRoleEnum::LENS);

		$this->assertContains('mcp__jira__createJiraIssue', $roleAccessObject->deniedTools);
		$this->assertContains('mcp__jira__executeWrite', $roleAccessObject->deniedTools);
	}

	#[Test]
	public function execute_leaves_an_agent_execute_write_for_its_operations(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['createJiraIssue', 'executeWrite'],
			'devdock.access.agent_operations' => ['createBitbucketRepoPullRequest'],
		]);

		$roleAccessObject = GetRoleAccessAction::execute(AgentRoleEnum::AGENT);

		$this->assertNotContains('mcp__jira__executeWrite', $roleAccessObject->deniedTools);
		$this->assertSame(['createBitbucketRepoPullRequest'], $roleAccessObject->writableOperations);
	}

	#[Test]
	public function execute_gives_a_helper_no_operations_to_write_through(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['executeWrite'],
			'devdock.access.agent_operations' => ['createBitbucketRepoPullRequest'],
		]);

		$roleAccessObject = GetRoleAccessAction::execute(AgentRoleEnum::HELPER);

		$this->assertSame([], $roleAccessObject->writableOperations);
		$this->assertContains('mcp__jira__executeWrite', $roleAccessObject->deniedTools);
	}

	#[Test]
	public function execute_denies_a_lens_the_tools_that_change_files(): void
	{
		$roleAccessObject = GetRoleAccessAction::execute(AgentRoleEnum::LENS);

		$this->assertContains('Edit', $roleAccessObject->deniedTools);
		$this->assertContains('Write', $roleAccessObject->deniedTools);
	}

	#[Test]
	public function execute_leaves_an_agent_the_tools_that_change_files(): void
	{
		$roleAccessObject = GetRoleAccessAction::execute(AgentRoleEnum::AGENT);

		$this->assertNotContains('Edit', $roleAccessObject->deniedTools);
		$this->assertNotContains('Write', $roleAccessObject->deniedTools);
	}
}
