<?php

declare(strict_types=1);

namespace Src\Session\Contracts;

use Src\Project\Objects\ProjectEntityCollection;
use Src\Session\Objects\SessionEntityCollection;
use Src\Session\Objects\SessionObjectCollection;
use Src\Session\Objects\TranscriptTurnObjectCollection;
use Src\Worktree\Objects\WorktreeEntityCollection;

interface SessionFileRepositoryContract
{
	/**
	 * Every session file in the sessions folder. A file whose fingerprint
	 * matches what is already stored is not read again; the largest files run
	 * into the tens of megabytes.
	 */
	public function getAll(
		ProjectEntityCollection $projectEntities,
		WorktreeEntityCollection $worktreeEntities,
		SessionEntityCollection $storedSessionEntities,
	): SessionObjectCollection;

	/**
	 * Writes a new name at the back of the session file and gives back the new
	 * fingerprint, or nothing when the file is gone.
	 */
	public function appendTitle(string $relativePath, string $uuid, string $title): ?string;

	public function delete(string $relativePath): bool;

	/**
	 * The first turns of a session file, to show in the panel.
	 */
	public function getTurns(string $relativePath, int $limit): TranscriptTurnObjectCollection;
}
