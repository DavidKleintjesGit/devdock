<?php

declare(strict_types=1);

namespace Src\Board\Enums;

/**
 * The columns of the board, one per step of the way of working. A task moves
 * from left to right as it is built, reviewed and merged.
 */
enum BoardColumnEnum: string
{
	case LATER = 'later';
	case TO_DO = 'todo';
	case BUILDING = 'building';
	case REVIEW = 'review';
	case PULL_REQUEST = 'pull_request';
	case DONE = 'done';

	public function label(): string
	{
		return __(match ($this) {
			self::LATER => 'Later',
			self::TO_DO => 'Te doen',
			self::BUILDING => 'Bouwen',
			self::REVIEW => 'Review',
			self::PULL_REQUEST => 'Pull request',
			self::DONE => 'Klaar',
		});
	}

	/**
	 * The colour of the dot in front of the column name.
	 */
	public function tone(): string
	{
		return match ($this) {
			self::LATER => 'faint',
			self::TO_DO => 'gray',
			self::BUILDING => 'teal',
			self::REVIEW => 'amber',
			self::PULL_REQUEST => 'blue',
			self::DONE => 'green',
		};
	}

	/**
	 * What lands in this column, for an empty one.
	 */
	public function emptyText(): string
	{
		return __(match ($this) {
			self::LATER => 'Niets in de backlog.',
			self::TO_DO => 'Haal iets uit de backlog, of maak een to-do.',
			self::BUILDING => 'Hier komt een taak zodra hij is ingericht.',
			self::REVIEW => 'Hier kijken de lenzen mee.',
			self::PULL_REQUEST => 'Hier wacht een taak op collega\'s en de merge.',
			self::DONE => 'Wat de laatste :days dagen gemerged is.',
		}, ['days' => (int) config('devdock.board.done_days')]);
	}

	/**
	 * Whether an empty column points at the backlog: that is where new work
	 * for the sprint comes from.
	 */
	public function offersBacklog(): bool
	{
		return $this === self::TO_DO;
	}

	/**
	 * Whether a task in this column can stall: only work that is under way.
	 */
	public function canStall(): bool
	{
		return in_array($this, [self::BUILDING, self::REVIEW, self::PULL_REQUEST], true);
	}

	/**
	 * The columns of the way of working, without the backlog.
	 *
	 * @return array<int, self>
	 */
	public static function sprint(): array
	{
		return array_values(array_filter(self::cases(), fn (self $column) => $column !== self::LATER));
	}
}
