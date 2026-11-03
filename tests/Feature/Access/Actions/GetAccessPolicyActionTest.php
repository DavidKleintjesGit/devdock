<?php

declare(strict_types=1);

namespace Tests\Feature\Access\Actions;

use PHPUnit\Framework\Attributes\Test;
use Src\Access\Actions\GetAccessPolicyAction;
use Src\Access\Enums\AgentRoleEnum;
use Tests\TestCase;

/**
 * @see GetAccessPolicyAction
 */
class GetAccessPolicyActionTest extends TestCase
{
	#[Test]
	public function execute_denies_every_writing_tool_on_every_listed_server(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['createJiraIssue', 'executeWrite'],
		]);

		$accessPolicyObject = GetAccessPolicyAction::execute(AgentRoleEnum::LENS);

		$this->assertContains('mcp__jira__createJiraIssue', $accessPolicyObject->deniedTools);
		$this->assertContains('mcp__jira__executeWrite', $accessPolicyObject->deniedTools);
	}

	#[Test]
	public function execute_leaves_an_agent_execute_write_for_its_operations(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['createJiraIssue', 'executeWrite'],
			'devdock.access.agent_operations' => ['createBitbucketRepoPullRequest'],
		]);

		$accessPolicyObject = GetAccessPolicyAction::execute(AgentRoleEnum::AGENT);

		$this->assertNotContains('mcp__jira__executeWrite', $accessPolicyObject->deniedTools);
		$this->assertSame(['createBitbucketRepoPullRequest'], $accessPolicyObject->writableOperations);
	}

	#[Test]
	public function execute_gives_a_helper_no_operations_to_write_through(): void
	{
		config([
			'devdock.access.servers' => ['jira'],
			'devdock.access.writing_tools' => ['executeWrite'],
			'devdock.access.agent_operations' => ['createBitbucketRepoPullRequest'],
		]);

		$accessPolicyObject = GetAccessPolicyAction::execute(AgentRoleEnum::HELPER);

		$this->assertSame([], $accessPolicyObject->writableOperations);
		$this->assertContains('mcp__jira__executeWrite', $accessPolicyObject->deniedTools);
	}

	#[Test]
	public function execute_denies_a_lens_the_tools_that_change_files(): void
	{
		$accessPolicyObject = GetAccessPolicyAction::execute(AgentRoleEnum::LENS);

		$this->assertContains('Edit', $accessPolicyObject->deniedTools);
		$this->assertContains('Write', $accessPolicyObject->deniedTools);
	}

	#[Test]
	public function execute_leaves_an_agent_the_tools_that_change_files(): void
	{
		$accessPolicyObject = GetAccessPolicyAction::execute(AgentRoleEnum::AGENT);

		$this->assertNotContains('Edit', $accessPolicyObject->deniedTools);
		$this->assertNotContains('Write', $accessPolicyObject->deniedTools);
	}
}
