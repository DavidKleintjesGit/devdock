<?php

declare(strict_types=1);

namespace Src\Worktree\Enums;

/**
 * Work on the working copy of a card that takes longer than a click may wait,
 * and so runs in a process of its own.
 */
enum WorkingCopyTaskEnum: string
{
	case LINK = 'link';
	case REBASE = 'rebase';
	case RESOLVE_CONFLICTS = 'resolve-conflicts';
	case PUSH = 'push';
	case OWN_TEST_DATABASE = 'own-test-database';
	case COMMIT_MESSAGE = 'commit-message';
	case BRANCH_NAME = 'branch-name';
	case COMMIT = 'commit';
	case PROMPT = 'prompt';
	case ROUND = 'round';
	case RETARGET = 'retarget';
	case CLEAN_UP = 'clean-up';
	case ARCHIVE = 'archive';
	case DELETE = 'delete';

	/**
	 * What the card says while the task runs.
	 */
	public function busyLabel(): string
	{
		return __(match ($this) {
			self::LINK => 'DevDock maakt de werkkopie',
			self::REBASE => 'DevDock haalt de doelbranch op en rebaset',
			self::RESOLVE_CONFLICTS => 'DevDock begint de rebase en zet de agent aan de conflicten',
			self::PUSH => 'DevDock pusht naar origin',
			self::OWN_TEST_DATABASE => 'DevDock maakt een eigen testdatabase',
			self::COMMIT_MESSAGE => 'DevDock schrijft een commitbericht bij de aangevinkte bestanden',
			self::BRANCH_NAME => 'DevDock bedenkt een branchnaam',
			self::COMMIT => 'DevDock legt de wijzigingen vast',
			self::PROMPT => 'DevDock geeft je bericht aan de agent',
			self::ROUND => 'DevDock roept de lenzen op',
			self::RETARGET => 'DevDock zet de taak op een andere doelbranch en rebaset',
			self::CLEAN_UP => 'DevDock ruimt de lokale spullen van deze taak op',
			self::ARCHIVE => 'DevDock ruimt deze taak op en archiveert hem',
			self::DELETE => 'DevDock verwijdert deze to-do',
		});
	}

	/**
	 * What a card on the board says while the task runs, short enough for one line.
	 */
	public function cardLabel(): string
	{
		return __(match ($this) {
			self::LINK => 'Werkkopie maken...',
			self::REBASE => 'Rebasen...',
			self::RESOLVE_CONFLICTS => 'Conflicten oplossen...',
			self::PUSH => 'Pushen...',
			self::OWN_TEST_DATABASE => 'Testdatabase maken...',
			self::COMMIT_MESSAGE => 'Commitbericht schrijven...',
			self::BRANCH_NAME => 'Branchnaam bedenken...',
			self::COMMIT => 'Committen...',
			self::PROMPT => 'Bericht naar de agent...',
			self::ROUND => 'Lenzen oproepen...',
			self::RETARGET => 'Verplaatsen...',
			self::CLEAN_UP => 'Opruimen...',
			self::ARCHIVE => 'Archiveren...',
			self::DELETE => 'Verwijderen...',
		});
	}

	/**
	 * What the card says when the task failed.
	 */
	public function failedLabel(): string
	{
		return __(match ($this) {
			self::LINK => 'De werkkopie kon niet gemaakt worden',
			self::REBASE => 'De rebase lukte niet',
			self::RESOLVE_CONFLICTS => 'De conflicten konden niet aan de agent gegeven worden',
			self::PUSH => 'Pushen lukte niet',
			self::OWN_TEST_DATABASE => 'De eigen testdatabase kon niet gemaakt worden',
			self::COMMIT_MESSAGE => 'Er kwam geen commitbericht',
			self::BRANCH_NAME => 'Er kwam geen branchnaam',
			self::COMMIT => 'Committen lukte niet',
			self::PROMPT => 'Je bericht kwam niet bij de agent',
			self::ROUND => 'Er ging geen ronde open',
			self::RETARGET => 'De doelbranch kon niet veranderd worden',
			self::CLEAN_UP => 'Opruimen lukte niet',
			self::ARCHIVE => 'Archiveren lukte niet',
			self::DELETE => 'Verwijderen lukte niet',
		});
	}
}
