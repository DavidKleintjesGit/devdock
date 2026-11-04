<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * The kinds of work Claude Code does in a working copy, as the gate tells
 * them apart: what may not run alongside what.
 */
enum WorkKindEnum: string
{
	case AGENT_TURN = 'agent_turn';
	// a turn of the agent that finishes the rebase DevDock began on "Conflicten oplossen"
	case CONFLICT_TURN = 'conflict_turn';
	case ROUND = 'round';
	case LENS = 'lens';
	case TERMINAL = 'terminal';

	/**
	 * Whether this work waits while an agent turn runs in the same copy.
	 */
	public function waitsForAgent(): bool
	{
		return in_array($this, [self::AGENT_TURN, self::CONFLICT_TURN, self::ROUND], true);
	}

	/**
	 * Whether this work waits while the lenses of a round run in the same copy.
	 */
	public function waitsForLenses(): bool
	{
		return $this === self::AGENT_TURN || $this === self::CONFLICT_TURN;
	}
}
