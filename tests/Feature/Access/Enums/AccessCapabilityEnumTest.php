<?php

declare(strict_types=1);

namespace Tests\Feature\Access\Enums;

use PHPUnit\Framework\Attributes\Test;
use Src\Access\Actions\GetRoleAccessAction;
use Src\Access\Enums\AccessAnswerEnum;
use Src\Access\Enums\AccessCapabilityEnum;
use Src\Access\Enums\AgentRoleEnum;
use Tests\TestCase;

/**
 * @see AccessCapabilityEnum
 */
class AccessCapabilityEnumTest extends TestCase
{
	#[Test]
	public function answer_for_lets_an_agent_open_a_pull_request_behind_the_guard(): void
	{
		config(['devdock.access.agent_operations' => [AccessCapabilityEnum::PULL_REQUEST_OPERATION]]);

		$accessAnswer = AccessCapabilityEnum::OPEN_PULL_REQUEST->answerFor(GetRoleAccessAction::execute(AgentRoleEnum::AGENT));

		$this->assertSame(AccessAnswerEnum::GUARDED, $accessAnswer);
	}

	#[Test]
	public function answer_for_shows_a_further_agent_operation_as_another_guarded_write(): void
	{
		config(['devdock.access.agent_operations' => [AccessCapabilityEnum::PULL_REQUEST_OPERATION, 'addCommentToJiraIssue']]);

		$accessAnswer = AccessCapabilityEnum::OTHER_ATLASSIAN_WRITES->answerFor(GetRoleAccessAction::execute(AgentRoleEnum::AGENT));

		$this->assertSame(AccessAnswerEnum::GUARDED, $accessAnswer);
	}

	#[Test]
	public function answer_for_refuses_a_lens_any_change_to_files(): void
	{
		$accessAnswer = AccessCapabilityEnum::CHANGE_FILES->answerFor(GetRoleAccessAction::execute(AgentRoleEnum::LENS));

		$this->assertSame(AccessAnswerEnum::NO, $accessAnswer);
	}
}
