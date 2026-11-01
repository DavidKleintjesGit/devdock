<?php

declare(strict_types=1);

namespace Src\Library\Enums;

/**
 * How a file in a project stands against its library.
 */
enum StandingEnum: string
{
	case EQUAL = 'equal';
	case BEHIND = 'behind';
	case MODIFIED = 'modified';
	case MISSING = 'missing';

	public function label(): string
	{
		return __(match ($this) {
			self::EQUAL => 'Gelijk',
			self::BEHIND => 'Loopt achter',
			self::MODIFIED => 'Zelf aangepast',
			self::MISSING => 'Ontbreekt',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::EQUAL => 'Zelfde inhoud als de bibliotheek. Regeleinden tellen niet mee.',
			self::BEHIND => 'Staat op een eerdere versie uit de bibliotheek. Bijwerken is veilig; de oude inhoud wordt eerst bewaard.',
			self::MODIFIED => 'Past bij geen enkele versie: iemand heeft hem in het project veranderd. Eerst vergelijken, of terugzetten als nieuwe versie.',
			self::MISSING => 'Het project volgt de bibliotheek, maar het bestand staat er niet. Projecten die hem niet volgen, tellen hier niet mee.',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::EQUAL => 'green',
			self::BEHIND => 'amber',
			self::MODIFIED => 'blue',
			self::MISSING => 'red',
		};
	}

	/**
	 * Whether this file asks for something to be done.
	 */
	public function deviates(): bool
	{
		return $this !== self::EQUAL;
	}
}
