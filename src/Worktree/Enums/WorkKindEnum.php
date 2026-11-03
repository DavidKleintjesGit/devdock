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
	case ROUND = 'round';
	case LENS = 'lens';
	case TERMINAL = 'terminal';

	/**
	 * Whether this work waits while an agent turn runs in the same copy.
	 */
	public function waitsForAgent(): bool
	{
		return $this === self::AGENT_TURN || $this === self::ROUND;
	}

	/**
	 * Whether this work waits while the lenses of a round run in the same copy.
	 */
	public function waitsForLenses(): bool
	{
		return $this === self::AGENT_TURN;
	}
}
