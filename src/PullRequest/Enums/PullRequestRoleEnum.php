<?php

declare(strict_types=1);

namespace Src\PullRequest\Enums;

/**
 * What you have to do with this pull request: you wrote it, or you were
 * asked to judge it.
 */
enum PullRequestRoleEnum: string
{
	case AUTHOR = 'author';
	case REVIEWER = 'reviewer';

	public function label(): string
	{
		return match ($this) {
			self::AUTHOR => __('Van mij'),
			self::REVIEWER => __('Ter review'),
		};
	}
}
