<?php

declare(strict_types=1);

namespace Src\Access\Enums;

use Src\Access\Objects\RoleAccessObject;

/**
 * One thing Claude Code could do in a task, as the Access page asks it of
 * every role.
 */
enum AccessCapabilityEnum: string
{
	case CHANGE_FILES = 'change_files';
	case READ_GIT = 'read_git';
	case WRITE_GIT = 'write_git';
	case READ_ATLASSIAN = 'read_atlassian';
	case OPEN_PULL_REQUEST = 'open_pull_request';
	case OTHER_ATLASSIAN_WRITES = 'other_atlassian_writes';

	/**
	 * The one Atlassian operation that opens a pull request.
	 */
	public const PULL_REQUEST_OPERATION = 'createBitbucketRepoPullRequest';

	public function label(): string
	{
		return __(match ($this) {
			self::CHANGE_FILES => 'Bestanden aanpassen',
			self::READ_GIT => 'Git lezen',
			self::WRITE_GIT => 'Git schrijven',
			self::READ_ATLASSIAN => 'Jira, Confluence en Bitbucket lezen',
			self::OPEN_PULL_REQUEST => 'Een pull request openen',
			self::OTHER_ATLASSIAN_WRITES => 'Iets anders schrijven in Atlassian',
		});
	}

	public function hint(): ?string
	{
		return match ($this) {
			self::CHANGE_FILES => __('in de werkkopie'),
			self::READ_GIT => __('status, diff, log, show, blame en de lijstvormen'),
			self::WRITE_GIT => __('commit, push, rebase, add, stash, reset, switch'),
			self::READ_ATLASSIAN => null,
			self::OPEN_PULL_REQUEST => __('alleen deze bewerking komt langs de schrijfbewaking'),
			self::OTHER_ATLASSIAN_WRITES => __('reacties, statussen, nieuwe tickets, Confluence-pagina\'s'),
		};
	}

	public function answerFor(RoleAccessObject $roleAccessObject): AccessAnswerEnum
	{
		$otherOperations = array_diff($roleAccessObject->writableOperations, [self::PULL_REQUEST_OPERATION]);

		return match ($this) {
			self::CHANGE_FILES => $roleAccessObject->agentRole->editsFiles() ? AccessAnswerEnum::YES : AccessAnswerEnum::NO,
			self::READ_GIT, self::READ_ATLASSIAN => AccessAnswerEnum::YES,
			self::WRITE_GIT => AccessAnswerEnum::NO,
			self::OPEN_PULL_REQUEST => in_array(self::PULL_REQUEST_OPERATION, $roleAccessObject->writableOperations, true) ? AccessAnswerEnum::GUARDED : AccessAnswerEnum::NO,
			self::OTHER_ATLASSIAN_WRITES => $otherOperations === [] ? AccessAnswerEnum::NO : AccessAnswerEnum::GUARDED,
		};
	}
}
