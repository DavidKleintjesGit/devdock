<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * What a new database of a working copy starts with.
 */
enum DatabaseFillEnum: string
{
	case COPY = 'copy';
	case MIGRATE = 'migrate';
	case EMPTY = 'empty';

	public function label(string $database): string
	{
		return __(match ($this) {
			self::COPY => 'Een kopie van :database',
			self::MIGRATE => 'Leeg, daarna de migraties',
			self::EMPTY => 'Helemaal leeg',
		}, ['database' => $database]);
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::COPY => 'Structuur en gegevens, zonder het logboek van Telescope. Je werkt verder waar het project nu staat.',
			self::MIGRATE => 'DevDock draait php artisan migrate --seed in de werkkopie.',
			self::EMPTY => 'Je richt hem zelf in.',
		});
	}
}
