<?php

declare(strict_types=1);

namespace Src\Prompt\Enums;

/**
 * Something an agent may ask DevDock to do for its task, which DevDock shows
 * as a button instead of the agent doing it itself.
 */
enum AgentActionEnum: string
{
	case TARGET = 'target';

	/**
	 * What the button says.
	 */
	public function label(string $argument): string
	{
		return match ($this) {
			self::TARGET => __('Zet op :branch', ['branch' => $argument]),
		};
	}
}
