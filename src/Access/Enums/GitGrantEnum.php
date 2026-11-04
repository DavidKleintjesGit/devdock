<?php

declare(strict_types=1);

namespace Src\Access\Enums;

/**
 * Git work you hand an agent with a button, for one turn only. Outside such a
 * turn an agent only reads git.
 */
enum GitGrantEnum: string
{
	case REBASE = 'rebase';

	public function label(): string
	{
		return __(match ($this) {
			self::REBASE => 'rebasen en conflicten oplossen',
		});
	}

	/**
	 * The git commands this grant lets through, next to the reading ones.
	 *
	 * @return list<string>
	 */
	public function commands(): array
	{
		return match ($this) {
			self::REBASE => ['rebase', 'add', 'rm', 'restore'],
		};
	}
}
