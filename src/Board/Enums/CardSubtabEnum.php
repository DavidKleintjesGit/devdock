<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * A subtab inside a tab of the card panel.
 */
enum CardSubtabEnum: string
{
	case CONVERSATION = 'gesprek';
	case CONTEXT = 'context';
	case CHANGES = 'wijzigingen';
	case BRANCH = 'branch';
	case SETUP = 'inrichting';
	case COPY = 'kopie';
	case ROUNDS = 'rondes';
	case LENSES = 'lenzen';

	public function label(): string
	{
		return __(match ($this) {
			self::CONVERSATION => 'Gesprek',
			self::CONTEXT => 'Context',
			self::CHANGES => 'Wijzigingen',
			self::BRANCH => 'Branch',
			self::SETUP => 'Inrichting',
			self::COPY => 'Deze kopie',
			self::ROUNDS => 'Rondes',
			self::LENSES => 'Lenzen',
		});
	}
}
