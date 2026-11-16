<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * The colour of a hint dot beside a tab: something that clashes and blocks,
 * or something that waits on you. A tab with both shows one dot in both colours.
 */
enum HintToneEnum: string
{
	case RED = 'red';
	case AMBER = 'amber';

	public function label(): string
	{
		return __(match ($this) {
			self::RED => 'Er botst iets',
			self::AMBER => 'Wacht op jou',
		});
	}
}
