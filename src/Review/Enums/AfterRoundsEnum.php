<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * What happens once the rounds run out and a lens still finds something.
 */
enum AfterRoundsEnum: string
{
	case BACK_TO_YOU = 'back';
	case CONTINUE_AFTER_APPROVAL = 'continue';
	case ONLY_BLOCKING = 'blocking';

	public function label(): string
	{
		return __(match ($this) {
			self::BACK_TO_YOU => 'Terug naar jou',
			self::CONTINUE_AFTER_APPROVAL => 'Doorgaan na akkoord',
			self::ONLY_BLOCKING => 'Alleen blokkerend',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::BACK_TO_YOU => 'De taak stopt met beoordelen en komt bij jou terug op het bord.',
			self::CONTINUE_AFTER_APPROVAL => 'Je kunt na het lezen nog een ronde toestaan.',
			self::ONLY_BLOCKING => 'Daarna tellen alleen bevindingen die de merge tegenhouden.',
		});
	}
}
