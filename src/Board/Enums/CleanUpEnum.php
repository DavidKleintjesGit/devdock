<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * Whether the local work of a task may be cleaned up.
 */
enum CleanUpEnum: string
{
	case MAY_CLEAN_UP = 'may_clean_up';
	case WORK_AT_STAKE = 'work_at_stake';

	/**
	 * What would be lost, in words; empty when nothing is.
	 */
	public static function workAtStake(int $uncommittedFiles, int $unpushedCommits): string
	{
		return implode(', ', array_filter([
			$uncommittedFiles > 0 ? trans_choice(':count bestand niet gecommit|:count bestanden niet gecommit', $uncommittedFiles, ['count' => $uncommittedFiles]) : null,
			$unpushedCommits > 0 ? trans_choice(':count commit staat nergens op origin|:count commits staan nergens op origin', $unpushedCommits, ['count' => $unpushedCommits]) : null,
		]));
	}
}
