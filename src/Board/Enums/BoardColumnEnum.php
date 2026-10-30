<?php

declare(strict_types=1);

namespace Src\Board\Enums;

enum BoardColumnEnum: string
{
	case TO_DO = 'todo';
	case IN_PROGRESS = 'busy';
	case REVIEW = 'review';
	case READY_TO_MERGE = 'merge';
	case DONE = 'done';

	public function label(): string
	{
		return __(match ($this) {
			self::TO_DO => 'Te doen',
			self::IN_PROGRESS => 'Mee bezig',
			self::REVIEW => 'Nakijken',
			self::READY_TO_MERGE => 'Klaar om te mergen',
			self::DONE => 'Klaar',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::REVIEW => 'amber',
			self::READY_TO_MERGE => 'green',
			self::IN_PROGRESS => 'teal',
			default => 'gray',
		};
	}

	/**
	 * Whether a task in this column is yours to pick up.
	 */
	public function waitsOnYou(): bool
	{
		return $this !== self::IN_PROGRESS && $this !== self::DONE;
	}

	/**
	 * Who a task in this column waits on; the board is ordered by that.
	 */
	public function waitsOn(): string
	{
		return __(match ($this) {
			self::TO_DO, self::REVIEW, self::READY_TO_MERGE => 'Wacht op jou',
			self::IN_PROGRESS => 'Wacht op een collega',
			self::DONE => 'Niemand meer',
		});
	}
}
