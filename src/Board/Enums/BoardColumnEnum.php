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
	case NOT_SET_UP = 'not_set_up';
	case BUILDING = 'building';
	case REVIEW = 'review';
	case PULL_REQUEST = 'pull_request';
	case DONE = 'done';

	public function label(): string
	{
		return __(match ($this) {
			self::LATER => 'Later',
			self::TO_DO => 'Te doen',
			self::NOT_SET_UP => 'Niet ingericht',
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
			self::NOT_SET_UP => 'gray',
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
			self::NOT_SET_UP => 'Hier staat een taak met een branch of agent, maar nog zonder werkkopie.',
			self::BUILDING => 'Hier komt een taak zodra hij een werkkopie heeft.',
			self::REVIEW => 'Hier staat een taak vanaf de eerste reviewronde tot zijn pull request.',
			self::PULL_REQUEST => 'Hier wacht een taak op collega\'s en de merge.',
			self::DONE => 'Wat de laatste :days dagen gemerged is.',
		}, ['days' => (int) config('devdock.board.done_days')]);
	}

	/**
	 * A choice of shown columns with "not set up" in front of building. That
	 * column split off from building, so a choice that shows building and
	 * predates it shows it too.
	 *
	 * @param  array<int, string>  $columns
	 * @return array<int, string>
	 */
	public static function withNotSetUp(array $columns): array
	{
		$buildingAt = array_search(self::BUILDING->value, $columns, true);

		if ($buildingAt === false || in_array(self::NOT_SET_UP->value, $columns, true)) {
			return $columns;
		}

		array_splice($columns, $buildingAt, 0, [self::NOT_SET_UP->value]);

		return $columns;
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
