<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

enum ChangeKindEnum: string
{
	case MODIFIED = 'modified';
	case ADDED = 'added';
	case DELETED = 'deleted';
	case RENAMED = 'renamed';
	case UNTRACKED = 'untracked';

	/**
	 * The letters git puts in front of a path in its short status.
	 */
	public static function fromStatus(string $status): self
	{
		return match (true) {
			str_contains($status, '?') => self::UNTRACKED,
			str_contains($status, 'R') => self::RENAMED,
			str_contains($status, 'D') => self::DELETED,
			str_contains($status, 'A') => self::ADDED,
			default => self::MODIFIED,
		};
	}

	public function label(): string
	{
		return __(match ($this) {
			self::MODIFIED => 'Gewijzigd',
			self::ADDED => 'Toegevoegd',
			self::DELETED => 'Verwijderd',
			self::RENAMED => 'Hernoemd',
			self::UNTRACKED => 'Nieuw',
		});
	}

	/**
	 * The icon in front of a file in a tree of changes, as Bitbucket shows it.
	 */
	public function icon(): string
	{
		return match ($this) {
			self::ADDED, self::UNTRACKED => 'plus',
			self::DELETED => 'minus',
			default => 'pencil',
		};
	}

	public function tone(): string
	{
		return match ($this) {
			self::DELETED => 'red',
			self::ADDED, self::UNTRACKED => 'green',
			default => 'amber',
		};
	}
}
