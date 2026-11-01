<?php

declare(strict_types=1);

namespace Src\Session\Enums;

/**
 * Something a session has, to filter on and to weigh whether it can go. The
 * traits under Bewaren plead for keeping it; the rest point the other way.
 */
enum SessionTraitEnum: string
{
	case NO_TICKET = 'no-ticket';
	case BRANCH_GONE = 'branch-gone';
	case WORKTREE_GONE = 'worktree-gone';
	case MERGED = 'merged';
	case FOLLOWED_UP = 'followed-up';
	case PROJECT_GONE = 'project-gone';
	case UNNAMED = 'unnamed';
	case NAME_MISMATCH = 'name-mismatch';
	case NOTHING_COMMITTED = 'nothing-committed';
	case ABANDONED = 'abandoned';
	case UNANSWERED = 'unanswered';
	case FALSE_START = 'false-start';
	case LARGE = 'large';
	case OLD = 'old';
	case OLDER_THAN_MONTH = 'month';
	case OLDER_THAN_QUARTER = 'quarter';
	case NEVER_RESUMED = 'never-resumed';
	case RUNNING_NOW = 'running';
	case OPEN_TASK = 'open-task';
	case UNSAVED_WORK = 'unsaved-work';
	case USED_THIS_WEEK = 'this-week';
	case PINNED = 'pinned';

	public function label(): string
	{
		return __(match ($this) {
			self::NO_TICKET => 'Zonder ticket',
			self::BRANCH_GONE => 'Branch weg',
			self::WORKTREE_GONE => 'Werkkopie weg',
			self::MERGED => 'Gemerged',
			self::FOLLOWED_UP => 'Opgevolgd',
			self::PROJECT_GONE => 'Project weg',
			self::UNNAMED => 'Zonder naam',
			self::NAME_MISMATCH => 'Naam ≠ ticket',
			self::NOTHING_COMMITTED => 'Niets gecommit',
			self::ABANDONED => 'Afgebroken',
			self::UNANSWERED => 'Onbeantwoord',
			self::FALSE_START => 'Valse start',
			self::LARGE => 'Groot',
			self::OLD => 'Ouder dan :days dagen',
			self::OLDER_THAN_MONTH => 'Ouder dan een maand',
			self::OLDER_THAN_QUARTER => 'Ouder dan drie maanden',
			self::NEVER_RESUMED => 'Nooit hervat',
			self::RUNNING_NOW => 'Draait nu',
			self::OPEN_TASK => 'Open taak',
			self::UNSAVED_WORK => 'Werk niet vastgelegd',
			self::USED_THIS_WEEK => 'Deze week gebruikt',
			self::PINNED => 'Vastgezet',
		}, ['days' => (int) config('devdock.sessions.old_after_days')]);
	}

	/**
	 * Why a session carries this trait, in the drawer of that session.
	 */
	public function explanation(): string
	{
		return __(match ($this) {
			self::NO_TICKET => 'Hangt aan geen enkele taak op het bord.',
			self::BRANCH_GONE => 'De branch waarop dit draaide bestaat niet meer.',
			self::WORKTREE_GONE => 'De werkkopie waarin dit draaide is opgeruimd.',
			self::MERGED => 'De pull request van deze branch is gemerged.',
			self::FOLLOWED_UP => 'Een nieuwere sessie werkte op dezelfde branch verder.',
			self::PROJECT_GONE => 'De map waarin dit draaide staat niet meer op schijf.',
			self::UNNAMED => 'Heeft nooit een naam gekregen.',
			self::NAME_MISMATCH => 'De naam volgt het ticket van de branch niet.',
			self::NOTHING_COMMITTED => 'Deze sessie heeft nooit een commit opgeleverd.',
			self::ABANDONED => 'Gestopt midden in een handeling en nooit hervat.',
			self::UNANSWERED => 'Je laatste bericht kreeg geen antwoord meer.',
			self::FALSE_START => 'Bleef bij een paar berichten.',
			self::LARGE => 'Neemt veel ruimte in op schijf.',
			self::OLD => ':days dagen niets meer mee gedaan.',
			self::OLDER_THAN_MONTH => 'Een maand niets meer mee gedaan.',
			self::OLDER_THAN_QUARTER => 'Drie maanden niets meer mee gedaan.',
			self::NEVER_RESUMED => 'Op één dag gevoerd en daarna nooit meer geopend.',
			self::RUNNING_NOW => 'Is op dit moment nog bezig.',
			self::OPEN_TASK => 'Hoort bij een taak die nog niet klaar is.',
			self::UNSAVED_WORK => 'In de map staan wijzigingen die nog niet gecommit zijn.',
			self::USED_THIS_WEEK => 'Deze week nog gebruikt.',
			self::PINNED => 'Je hebt hem vastgezet; hij wordt nooit opgeruimd.',
		}, ['days' => (int) config('devdock.sessions.old_after_days')]);
	}

	public function group(): string
	{
		return __(match ($this) {
			self::NO_TICKET, self::BRANCH_GONE, self::WORKTREE_GONE, self::MERGED,
			self::FOLLOWED_UP, self::PROJECT_GONE, self::UNNAMED, self::NAME_MISMATCH => 'Koppeling',
			self::NOTHING_COMMITTED, self::ABANDONED, self::UNANSWERED, self::FALSE_START, self::LARGE => 'Uitkomst',
			self::OLD, self::OLDER_THAN_MONTH, self::OLDER_THAN_QUARTER, self::NEVER_RESUMED => 'Tijd',
			self::RUNNING_NOW, self::OPEN_TASK, self::UNSAVED_WORK, self::USED_THIS_WEEK, self::PINNED => 'Bewaren',
		});
	}

	/**
	 * Whether this trait pleads for keeping the session. One of these is
	 * enough to keep it out of a clean-up.
	 */
	public function protects(): bool
	{
		return in_array($this, [
			self::RUNNING_NOW,
			self::OPEN_TASK,
			self::UNSAVED_WORK,
			self::USED_THIS_WEEK,
			self::PINNED,
		], true);
	}
}
