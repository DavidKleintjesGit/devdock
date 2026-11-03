<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * What a card on the board still needs before an agent can work on it.
 */
enum LinkStepEnum: string
{
	case READY = 'ready';
	case REPAIR = 'repair';
	case LINK = 'link';
	case NEEDS_BRANCH = 'needs_branch';

	public function label(): string
	{
		return __(match ($this) {
			self::READY => 'gereed',
			self::REPAIR => 'herstellen',
			self::LINK => 'koppelen',
			self::NEEDS_BRANCH => 'eerst een branch kiezen',
		});
	}
}
