<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The database a working copy points at, as its own .env says.
 */
class DatabaseObject extends ValueObject
{
	public function __construct(
		public readonly string $connection,
		public readonly ?string $host,
		public readonly ?string $port,
		public readonly ?string $database,
		public readonly ?string $projectDatabase,
		public readonly bool $isShared,
	) {
		parent::__construct();
	}

	public function isSqlite(): bool
	{
		return $this->connection === 'sqlite';
	}

	/**
	 * Where it lives, in one line: the driver and the server.
	 */
	public function serverLabel(): string
	{
		return $this->isSqlite()
			? 'sqlite · ' . __('een bestand')
			: $this->connection . ' · ' . $this->host . ':' . $this->port;
	}

	/**
	 * The name a database of this task would get: the project database with
	 * the ticket or branch behind it.
	 */
	public function suggestionFor(string $suffix): string
	{
		$base = $this->isSqlite()
			? pathinfo((string) $this->projectDatabase, PATHINFO_FILENAME) ?: 'database'
			: (string) $this->projectDatabase;

		return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $base . '_' . $suffix));
	}
}
