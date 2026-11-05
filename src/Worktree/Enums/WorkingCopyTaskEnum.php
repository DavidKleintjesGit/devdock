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
		});
	}
}
