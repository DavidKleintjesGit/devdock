<?php

declare(strict_types=1);

namespace Src\Library\Enums;

/**
 * Where a version came from. Every way in makes a new version; nothing is
 * ever overwritten.
 */
enum VersionSourceEnum: string
{
	case FOLDER = 'folder';
	case EDIT = 'edit';
	case PROJECT = 'project';
	case RESTORE = 'restore';

	public function label(string $library, ?string $project): string
	{
		return __(match ($this) {
			self::FOLDER => 'binnengehaald uit :library',
			self::EDIT => 'bewerkt in DevDock',
			self::PROJECT => 'teruggezet vanuit :project',
			self::RESTORE => 'teruggehaald uit een eerdere versie',
		}, ['library' => $library, 'project' => (string) $project]);
	}
}
