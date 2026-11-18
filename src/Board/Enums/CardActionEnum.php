<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * What you can do with tasks you no longer work on, from light to final.
 */
enum CardActionEnum: string
{
	case CLEAN_UP = 'clean_up';
	case ARCHIVE = 'archive';
	case DELETE = 'delete';

	public function label(): string
	{
		return __(match ($this) {
			self::CLEAN_UP => 'Opruimen',
			self::ARCHIVE => 'Archiveren',
			self::DELETE => 'Verwijderen',
		});
	}

	/**
	 * What the action does, in one line above the list of what goes.
	 */
	public function explanation(): string
	{
		return __(match ($this) {
			self::CLEAN_UP => 'De lokale spullen gaan weg; de kaart blijft op het bord en het gesprek blijft leesbaar.',
			self::ARCHIVE => 'De lokale spullen gaan weg en de kaart gaat van het bord. Het gesprek blijft, dus terugkijken en heropenen kan.',
			self::DELETE => 'Alles van deze to-do gaat weg, ook het gesprek en de rondes. Dit kan niet terug.',
		});
	}

	/**
	 * Whether the history of the task goes too: only a to-do lives nowhere else.
	 */
	public function deletesHistory(): bool
	{
		return $this === self::DELETE;
	}
}
