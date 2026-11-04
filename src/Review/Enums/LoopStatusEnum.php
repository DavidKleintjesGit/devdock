<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Where the review loop of a task stands: who is busy, or why it stopped.
 */
enum LoopStatusEnum: string
{
	case IDLE = 'idle';
	case LENSES = 'lenses';
	case AGENT = 'agent';
	case WAITING = 'waiting';
	case AGENT_ASKS = 'agent_asks';
	case STOPPED = 'stopped';
	case CLEAN = 'clean';
	case BROKEN_OFF = 'broken_off';
	case LENS_FELL = 'lens_fell';
	// the agent reported done, but the next round did not start by itself
	case NEXT_ROUND = 'next_round';

	public function label(int $position, int $max, string $findings): string
	{
		return match ($this) {
			self::IDLE => __('Nog geen ronde gedraaid'),
			self::LENSES => __('Ronde :position van :max · de lenzen kijken', ['position' => $position, 'max' => $max]),
			self::AGENT => __('De agent werkt de bevindingen van ronde :position bij', ['position' => $position]),
			self::WAITING => __('Ronde :position niet schoon · de agent pakt het zo op', ['position' => $position]),
			self::AGENT_ASKS => __('Ronde :position · de agent wacht op jou', ['position' => $position]),
			self::STOPPED => __('Loop gestopt na :max rondes · nog :findings', ['max' => $max, 'findings' => $findings]),
			self::CLEAN => __('Ronde :position schoon · klaar om te committen', ['position' => $position]),
			self::BROKEN_OFF => __('Ronde :position afgebroken', ['position' => $position]),
			self::LENS_FELL => __('Ronde :position · :findings · zet die lens opnieuw uit', ['position' => $position, 'findings' => $findings]),
			self::NEXT_ROUND => __('De agent is klaar met ronde :position · de volgende ronde startte niet vanzelf', ['position' => $position]),
		};
	}

	/**
	 * The colour of the dot in front of the status.
	 */
	public function tone(): string
	{
		return match ($this) {
			self::LENSES, self::AGENT, self::WAITING => 'busy',
			self::STOPPED, self::AGENT_ASKS, self::LENS_FELL, self::NEXT_ROUND => 'red',
			self::CLEAN => 'green',
			self::IDLE, self::BROKEN_OFF => 'quiet',
		};
	}

	/**
	 * Whether the loop waits on you: after the last round of a series, or
	 * when the agent asked something or left points open.
	 */
	public function needsYou(): bool
	{
		return in_array($this, [self::STOPPED, self::AGENT_ASKS, self::NEXT_ROUND], true);
	}
}
