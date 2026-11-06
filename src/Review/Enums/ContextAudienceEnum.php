<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Who is given a piece of context: the agent, a lens, or both.
 */
enum ContextAudienceEnum: string
{
	case AGENT = 'agent';
	case LENS = 'lens';
	case BOTH = 'both';

	public static function of(ContextChoiceEnum $contextChoice): self
	{
		return match ($contextChoice) {
			ContextChoiceEnum::AGENT => self::AGENT,
			ContextChoiceEnum::LENS => self::LENS,
			ContextChoiceEnum::RULES, ContextChoiceEnum::SKILLS => self::BOTH,
		};
	}

	/**
	 * The one word in the list of sources.
	 */
	public function shortLabel(): string
	{
		return __(match ($this) {
			self::AGENT => 'agent',
			self::LENS => 'lens',
			self::BOTH => 'allebei',
		});
	}

	public function label(): string
	{
		return __(match ($this) {
			self::AGENT => 'de agent',
			self::LENS => 'deze lens',
			self::BOTH => 'agent en lenzen',
		});
	}
}
