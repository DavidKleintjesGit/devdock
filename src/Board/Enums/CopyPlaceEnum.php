<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * Where the branch of a task is checked out.
 */
enum CopyPlaceEnum: string
{
	case OWN = 'own';
	case MAIN_CHECKOUT = 'main-checkout';
	case NONE = 'none';

	public function label(): string
	{
		return __(match ($this) {
			self::OWN => 'Eigen werkkopie',
			self::MAIN_CHECKOUT => 'Staat in de hoofdcheckout',
			self::NONE => 'Nog geen werkkopie',
		});
	}

	/**
	 * The symbol of the place in the icon sprite.
	 */
	public function icon(): string
	{
		return match ($this) {
			self::OWN => 'i-folder',
			self::MAIN_CHECKOUT => 'i-home',
			self::NONE => 'i-folder-none',
		};
	}

	/**
	 * Whether the card shows it: only what differs from a working copy of its own.
	 */
	public function isWorthShowing(): bool
	{
		return $this !== self::OWN;
	}
}
