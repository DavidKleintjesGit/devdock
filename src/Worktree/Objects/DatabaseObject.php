<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The database a working copy points at, as its own .env says, and the
 * other connections its migrations write to that still point at the
 * project's own database.
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
		/** @var list<string> */
		public readonly array $sharedConnections = [],
	) {
		parent::__construct();
	}

	/**
	 * Whether anything of this copy still writes to a database of the project.
	 */
	public function touchesProject(): bool
	{
		return $this->isShared || $this->sharedConnections !== [];
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
