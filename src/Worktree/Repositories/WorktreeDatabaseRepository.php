<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PDO;
use Src\Packages\Process\DetachedCommand;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Objects\DatabaseObject;
use Throwable;

class WorktreeDatabaseRepository implements WorktreeDatabaseRepositoryContract
{
	/**
	 * Databases every server carries that no project works in.
	 */
	private const SYSTEM_DATABASES = ['information_schema', 'mysql', 'performance_schema', 'sys'];

	/**
	 * Where Laravel reads the settings of a test run from.
	 */
	private const TESTING_FILE = '.env.testing';

	public function describe(string $repositoryPath, string $path): DatabaseObject
	{
		$own = $this->environment($path);
		$project = $this->environment($repositoryPath);
		$connection = $own['DB_CONNECTION'] ?? 'sqlite';

		$database = $connection === 'sqlite'
			? $this->sqlitePath($path, $own['DB_DATABASE'] ?? null)
			: ($own['DB_DATABASE'] ?? null);

		$projectDatabase = $connection === 'sqlite'
			? $this->sqlitePath($repositoryPath, $project['DB_DATABASE'] ?? null)
			: ($project['DB_DATABASE'] ?? null);

		return new DatabaseObject(
			connection: $connection,
			host: $own['DB_HOST'] ?? '127.0.0.1',
			port: $own['DB_PORT'] ?? '3306',
			database: $database,
			projectDatabase: $projectDatabase,
			isShared: $database !== null && $database === $projectDatabase,
		);
	}

	public function getDatabases(string $path): array
	{
		$environment = $this->environment($path);

		if (($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
			return array_values(array_map(
				fn (string $file) => str_replace('\\', '/', $file),
				glob($path . '/database/*.sqlite') ?: [],
			));
		}

		try {
			$names = $this->server($environment)->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
		} catch (Throwable) {
			return [];
		}

		return array_values(array_diff($names, self::SYSTEM_DATABASES));
	}

	public function exists(string $path, string $name): bool
	{
		$environment = $this->environment($path);

		if (($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
			return is_file($this->sqliteFileFor($path, $name));
		}

		return in_array($name, $this->getDatabases($path), true);
	}

	public function create(string $repositoryPath, string $path, string $name, DatabaseFillEnum $fill): ?string
	{
		if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
			return __('Een databasenaam bestaat alleen uit letters, cijfers en lage streepjes.');
		}

		if ($this->exists($path, $name)) {
			return __('De database :name bestaat al op deze server.', ['name' => $name]);
		}

		$environment = $this->environment($path);
		$describe = $this->describe($repositoryPath, $path);
		$server = null;

		// een kopie of de migraties lopen langer dan de 30 seconden van een gewoon verzoek
		set_time_limit(600);

		try {
			if ($describe->isSqlite()) {
				$file = $this->sqliteFileFor($path, $name);
				File::ensureDirectoryExists(dirname($file));

				$fill === DatabaseFillEnum::COPY && is_file((string) $describe->projectDatabase)
					? File::copy((string) $describe->projectDatabase, $file)
					: File::put($file, '');

				$this->use($path, $file);
			} else {
				$server = $this->server($environment);
				$server->exec('CREATE DATABASE `' . $name . '`');

				if ($fill === DatabaseFillEnum::COPY && $describe->projectDatabase !== null) {
					$this->copyTables($server, $describe->projectDatabase, $name);
				}

				$this->use($path, $name);
			}
		} catch (Throwable $failure) {
			// een half gekopieerde database blijft niet staan, anders bestaat de naam al bij de volgende poging
			if ($server !== null && $this->exists($path, $name)) {
				$server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
			}

			return $failure->getMessage();
		}

		if ($fill === DatabaseFillEnum::MIGRATE) {
			$migrated = Process::path($path)
				->timeout(600)
				->run([resolve(DetachedCommand::class)->phpBinary(), 'artisan', 'migrate', '--seed', '--force']);

			if (! $migrated->successful()) {
				return trim($migrated->errorOutput() ?: $migrated->output()) ?: __('De migraties liepen vast.');
			}
		}

		return null;
	}

	public function use(string $path, string $name): void
	{
		$this->pointAt($path, '.env', $name);
	}

	public function describeTesting(string $repositoryPath, string $path): ?DatabaseObject
	{
		$own = $this->environment($path, self::TESTING_FILE);
		$project = $this->environment($repositoryPath, self::TESTING_FILE);

		// a sqlite test database lives in memory or in its own file; only a server is shared
		if (($own['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
			return null;
		}

		return new DatabaseObject(
			connection: (string) $own['DB_CONNECTION'],
			host: $own['DB_HOST'] ?? '127.0.0.1',
			port: $own['DB_PORT'] ?? '3306',
			database: $own['DB_DATABASE'] ?? null,
			projectDatabase: $project['DB_DATABASE'] ?? null,
			isShared: ($own['DB_DATABASE'] ?? null) !== null && ($own['DB_DATABASE'] ?? null) === ($project['DB_DATABASE'] ?? null),
		);
	}

	public function giveOwnTestDatabase(string $repositoryPath, string $path, string $branch): ?string
	{
		$describe = $this->describeTesting($repositoryPath, $path);

		if ($describe === null || blank($describe->projectDatabase)) {
			return null;
		}

		// the part after the last slash names the task: feature/CSM-3554 becomes test_partnerportal_csm_3554
		$suffix = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(basename($branch))), '_');
		$name = $describe->isShared ? substr($describe->projectDatabase . '_' . $suffix, 0, 64) : (string) $describe->database;

		if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
			return null;
		}

		try {
			$server = $this->server($this->environment($path, self::TESTING_FILE));
			$server->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '`');

			// a test run that does not migrate expects the tables of the project's test database
			if ($this->tableCount($server, $name) === 0) {
				$this->copyTables($server, $describe->projectDatabase, $name);
			}
		} catch (Throwable $failure) {
			return $failure->getMessage();
		}

		$this->pointAt($path, self::TESTING_FILE, $name);

		return null;
	}

	private function tableCount(PDO $server, string $database): int
	{
		$count = $server->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
		$count->execute([$database]);

		return (int) $count->fetchColumn();
	}

	/**
	 * Points DB_DATABASE in one env file of the working copy at a database.
	 */
	private function pointAt(string $path, string $envFile, string $name): void
	{
		$file = $path . '/' . $envFile;
		$contents = is_file($file) ? (string) file_get_contents($file) : '';
		$line = 'DB_DATABASE=' . $name;

		$contents = preg_match('/^#?\s*DB_DATABASE=.*$/m', $contents) === 1
			? (string) preg_replace('/^#?\s*DB_DATABASE=.*$/m', $line, $contents, 1)
			: rtrim($contents, "\n") . "\n" . $line . "\n";

		file_put_contents($file, $contents);
	}

	public function ownTestDatabase(string $repositoryPath, string $path): ?string
	{
		$describe = $this->describeTesting($repositoryPath, $path);

		// only the test database DevDock made for this copy: the one of the project with a suffix of the task
		return $describe === null || $describe->isShared || blank($describe->projectDatabase) || ! str_starts_with((string) $describe->database, $describe->projectDatabase . '_')
			? null
			: $describe->database;
	}

	public function dropTestDatabase(string $repositoryPath, string $path): void
	{
		$name = $this->ownTestDatabase($repositoryPath, $path);

		if ($name !== null && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
			$this->server($this->environment($path, self::TESTING_FILE))->exec('DROP DATABASE IF EXISTS `' . $name . '`');
		}
	}

	public function drop(string $path, string $name): void
	{
		$environment = $this->environment($path);

		if (($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
			@unlink($this->sqliteFileFor($path, $name));

			return;
		}

		if (preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
			$this->server($environment)->exec('DROP DATABASE IF EXISTS `' . $name . '`');
		}
	}

	/**
	 * Copies every table with its rows, except the Telescope and Pulse logs,
	 * which come along empty; the server does the work, so nothing passes
	 * through here.
	 */
	private function copyTables(PDO $server, string $from, string $to): void
	{
		$tables = $server->query('SHOW FULL TABLES FROM `' . $from . '` WHERE Table_type = \'BASE TABLE\'')
			->fetchAll(PDO::FETCH_COLUMN);

		$server->exec('SET FOREIGN_KEY_CHECKS = 0');

		// een kopie neemt over wat er staat, ook rijen die de strenge modus nu zou weigeren (zoals 0000-00-00)
		$sqlMode = (string) $server->query('SELECT @@SESSION.sql_mode')->fetchColumn();
		$server->exec("SET SESSION sql_mode = ''");

		// een gegenereerde kolom rekent de server zelf uit; een waarde erin zetten weigert hij
		$columns = $server->prepare(
			'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
			. ' WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COALESCE(GENERATION_EXPRESSION, \'\') = \'\''
			. ' ORDER BY ORDINAL_POSITION',
		);

		foreach ($tables as $table) {
			$columns->execute([$from, $table]);
			$copied = implode(', ', array_map(
				fn (string $column) => '`' . str_replace('`', '``', $column) . '`',
				$columns->fetchAll(PDO::FETCH_COLUMN),
			));

			$server->exec(sprintf('CREATE TABLE `%s`.`%s` LIKE `%s`.`%s`', $to, $table, $from, $table));

			// het logboek van Telescope en Pulse is vaak het grootste deel van de database en zegt niets over de nieuwe taak
			if (Str::startsWith($table, ['telescope_', 'pulse_'])) {
				continue;
			}

			$server->exec(sprintf('INSERT INTO `%s`.`%s` (%s) SELECT %s FROM `%s`.`%s`', $to, $table, $copied, $copied, $from, $table));
		}

		$server->prepare('SET SESSION sql_mode = ?')->execute([$sqlMode]);
		$server->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	/**
	 * A connection to the server itself, with the account of the working
	 * copy, which is the account of the project.
	 *
	 * @param  array<string, string|null>  $environment
	 */
	private function server(array $environment): PDO
	{
		$dsn = sprintf(
			'mysql:host=%s;port=%s',
			$environment['DB_HOST'] ?? '127.0.0.1',
			$environment['DB_PORT'] ?? '3306',
		);

		return new PDO($dsn, $environment['DB_USERNAME'] ?? 'root', $environment['DB_PASSWORD'] ?? '', [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_TIMEOUT => 5,
		]);
	}

	/**
	 * @return array<string, string|null>
	 */
	private function environment(string $path, string $envFile = '.env'): array
	{
		$file = $path . '/' . $envFile;

		if (! is_file($file)) {
			return [];
		}

		try {
			return Dotenv::parse((string) file_get_contents($file));
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * Where a sqlite database stands: what the .env says, or Laravel's own
	 * default in the database folder.
	 */
	private function sqlitePath(string $path, ?string $database): string
	{
		$file = blank($database) ? $path . '/database/database.sqlite' : (string) $database;

		if (! str_starts_with($file, '/') && preg_match('/^[A-Za-z]:/', $file) !== 1) {
			$file = $path . '/' . $file;
		}

		return str_replace('\\', '/', realpath($file) ?: $file);
	}

	private function sqliteFileFor(string $path, string $name): string
	{
		return str_contains($name, '/') ? $name : $path . '/database/' . $name . '.sqlite';
	}
}
