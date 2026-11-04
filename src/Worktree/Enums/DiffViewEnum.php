<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Which changes of a working copy the code reader shows, and against what.
 */
enum DiffViewEnum: string
{
	case UNCOMMITTED = 'uncommitted';
	case UNPUSHED = 'unpushed';
	case BRANCH = 'branch';
	// what one turn of the agent changed, between its two snapshots
	case ROUND = 'round';

	public function label(): string
	{
		return __(match ($this) {
			self::UNCOMMITTED => 'Niet gecommit',
			self::UNPUSHED => 'Niet gepusht',
			self::BRANCH => 'Hele PR',
			self::ROUND => 'Deze ronde',
		});
	}
}
