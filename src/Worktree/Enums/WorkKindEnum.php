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
	 * Whether this work waits while an agent turn runs in the same copy: one
	 * agent works in a copy at a time.
	 */
	public function waitsForAgent(): bool
	{
		return $this === self::AGENT_TURN || $this === self::CONFLICT_TURN;
	}

	/**
	 * Whether this work waits while the agent finishes a rebase in the same
	 * copy. A round goes alongside every other turn of the agent; a rebase
	 * moves the whole branch under it.
	 */
	public function waitsForRebase(): bool
	{
		return $this === self::ROUND;
	}

	/**
	 * Whether this work waits while the lenses of a round run in the same copy.
	 * Only a rebase does; a turn of the agent goes alongside the lenses.
	 */
	public function waitsForLenses(): bool
	{
		return $this === self::CONFLICT_TURN;
	}
}
