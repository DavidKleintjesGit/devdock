<?php

declare(strict_types=1);

namespace Src\PullRequest\Enums;

enum PullRequestStateEnum: string
{
	case OPEN = 'OPEN';
	case MERGED = 'MERGED';

	public function label(): string
	{
		return match ($this) {
			self::OPEN => __('Open'),
			self::MERGED => __('Gemerged'),
		};
	}
}
