<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Who decides what happens with the findings of a round: you, after the agent
 * assessed them, or the agent by itself up to the threshold.
 */
enum ReviewModeEnum: string
{
	case HUMAN = 'human';
	case AUTOMATIC = 'automatic';

	public function label(): string
	{
		return __(match ($this) {
			self::HUMAN => 'Zelf beoordelen',
			self::AUTOMATIC => 'Automatisch',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::HUMAN => 'Na elke ronde beoordeelt de agent de bevindingen zonder iets te veranderen; jij kiest wat er gefixt wordt. Na de fix start één nieuwe ronde.',
			self::AUTOMATIC => 'De agent fixt de bevindingen zelf en de lenzen gaan opnieuw, tot de drempel.',
		});
	}

	/**
	 * Whether a round that found something goes back to the agent to be fixed without asking you.
	 */
	public function fixesByItself(): bool
	{
		return $this === self::AUTOMATIC;
	}
}
