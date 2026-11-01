<?php

declare(strict_types=1);

namespace Src\Task\Enums;

/**
 * The things a task can be tied to. A pinned tie always beats the one the
 * board works out from names, and of every kind exactly one is active.
 */
enum TaskLinkKindEnum: string
{
	case TICKET = 'ticket';
	case PROJECT = 'project';
	case BRANCH = 'branch';
	case PULL_REQUEST = 'pull_request';

	/**
	 * Another project this task may look into. Of this kind there is no
	 * active one: every project that is tied here counts.
	 */
	case READABLE_PROJECT = 'readable_project';

	/**
	 * Whether a value of this kind belongs to one task only, so tying it here
	 * takes it off any other. Many tasks share a project.
	 */
	public function isExclusive(): bool
	{
		return match ($this) {
			self::TICKET, self::BRANCH, self::PULL_REQUEST => true,
			self::PROJECT, self::READABLE_PROJECT => false,
		};
	}

	public function label(): string
	{
		return __(match ($this) {
			self::TICKET => 'Ticket',
			self::PROJECT => 'Project',
			self::BRANCH => 'Branch',
			self::PULL_REQUEST => 'Pull request',
			self::READABLE_PROJECT => 'Mag inzien',
		});
	}

	/**
	 * The symbol this kind is drawn with in the card.
	 */
	public function icon(): string
	{
		return match ($this) {
			self::TICKET => 'file',
			self::PROJECT => 'folder',
			self::BRANCH => 'branch',
			self::PULL_REQUEST => 'pr',
			self::READABLE_PROJECT => 'folder',
		};
	}
}
