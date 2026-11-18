<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PDO;
use Src\Project\Contracts\ProjectPhpRepositoryContract;
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

	/**
	 * The keys a Laravel app reads its database from when its config names none of its own.
	 */
	private const STANDARD_KEYS = [
		'driver' => null,
		'database' => 'DB_DATABASE',
		'databaseDefault' => null,
		'host' => 'DB_HOST',
		'port' => 'DB_PORT',
		'username' => 'DB_USERNAME',
		'password' => 'DB_PASSWORD',
	];

	/** @var array<string, array<string, array<string, string|null>>> the connections of the config per working copy, read once */
	private array $connectionsByPath = [];

	/** @var array<string, list<string>> the connections the migrations write to per working copy, read once */
	private array $migratedByPath = [];

	public function describe(string $repositoryPath, string $path): DatabaseObject
	{
		return $this->describeIn($repositoryPath, $path, '.env');
	}

	public function getDatabases(string $path): array
	{
		$environment = $this->environment($path);
		$keys = $this->defaultKeys($path, $environment);

		if ($keys['driver'] === 'sqlite') {
			return array_values(array_map(
				fn (string $file) => str_replace('\\', '/', $file),
				glob($path . '/database/*.sqlite') ?: [],
			));
		}

		try {
			$names = $this->server($environment, $keys)->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
		} catch (Throwable) {
			return [];
		}

		return array_values(array_diff($names, self::SYSTEM_DATABASES));
	}

	public function exists(string $path, string $name): bool
	{
		if ($this->defaultKeys($path, $this->environment($path))['driver'] === 'sqlite') {
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
				$server = $this->server($environment, $this->defaultKeys($path, $environment));
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
				->run([resolve(ProjectPhpRepositoryContract::class)->binaryFor($path), 'artisan', 'migrate', '--seed', '--force']);

			if (! $migrated->successful()) {
				return trim($migrated->errorOutput() ?: $migrated->output()) ?: __('De migraties liepen vast.');
			}
		}

		return null;
	}

	public function use(string $path, string $name): void
	{
		$this->pointAt($path, '.env', (string) $this->defaultKeys($path, $this->environment($path))['database'], $name);
	}

	public function describeTesting(string $repositoryPath, string $path): ?DatabaseObject
	{
		$describe = $this->describeIn($repositoryPath, $path, self::TESTING_FILE);

		// a sqlite test database lives in memory or in its own file; only a server is shared
		return $describe->isSqlite() || ! is_file($path . '/' . self::TESTING_FILE) ? null : $describe;
	}

	public function giveOwnTestDatabase(string $repositoryPath, string $path, string $branch): ?string
	{
		$describe = $this->describeTesting($repositoryPath, $path);

		if ($describe === null || blank($describe->projectDatabase)) {
			return null;
		}

		$name = $describe->isShared ? $this->nameFor((string) $describe->projectDatabase, $branch) : (string) $describe->database;

		if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
			return null;
		}

		$environment = $this->environment($path, self::TESTING_FILE);
		$keys = $this->defaultKeys($path, $environment);

		try {
			$this->createAndFill($this->server($environment, $keys), (string) $describe->projectDatabase, $name);
		} catch (Throwable $failure) {
			return $failure->getMessage();
		}

		$this->pointAt($path, self::TESTING_FILE, (string) $keys['database'], $name);

		return null;
	}

	public function giveOwnConnections(string $repositoryPath, string $path, string $branch, bool $forTesting): ?string
	{
		$envFile = $forTesting ? self::TESTING_FILE : '.env';
		$own = $this->environment($path, $envFile);
		$project = $this->environment($repositoryPath, $envFile);

		foreach ($this->sharedConnections($path, $own, $project) as $connection) {
			$keys = $this->connections($path)[$connection];
			$projectDatabase = (string) $this->valueOf($keys, $project);
			$name = $this->nameFor($projectDatabase, $branch);

			if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1 || $keys['database'] === null) {
				return __('DevDock kan de database van de verbinding :connection niet omzetten.', ['connection' => $connection]);
			}

			try {
				$this->createAndFill($this->server($own, $keys, $this->defaultKeys($path, $own)), $projectDatabase, $name);
			} catch (Throwable $failure) {
				return $connection . ': ' . $failure->getMessage();
			}

			$this->pointAt($path, $envFile, (string) $keys['database'], $name);
		}

		return null;
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
			$environment = $this->environment($path, self::TESTING_FILE);
			$this->server($environment, $this->defaultKeys($path, $environment))->exec('DROP DATABASE IF EXISTS `' . $name . '`');
		}
	}

	public function drop(string $path, string $name): void
	{
		$environment = $this->environment($path);
		$keys = $this->defaultKeys($path, $environment);

		if ($keys['driver'] === 'sqlite') {
			@unlink($this->sqliteFileFor($path, $name));

			return;
		}

		if (preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
			$this->server($environment, $keys)->exec('DROP DATABASE IF EXISTS `' . $name . '`');
		}
	}

	/**
	 * Which database the default connection of the working copy uses in one
	 * env file, and which of the other connections its migrations write to
	 * still point at the project's own database. A database the copy cannot
	 * name counts as the project's: nothing may be run on a guess.
	 */
	private function describeIn(string $repositoryPath, string $path, string $envFile): DatabaseObject
	{
		$own = $this->environment($path, $envFile);
		$project = $this->environment($repositoryPath, $envFile);
		$keys = $this->defaultKeys($path, $own);
		$driver = (string) $keys['driver'];

		$database = $driver === 'sqlite'
			? $this->sqlitePath($path, $this->valueOf($keys, $own))
			: $this->valueOf($keys, $own);

		$projectDatabase = $driver === 'sqlite'
			? $this->sqlitePath($repositoryPath, $this->valueOf($keys, $project))
			: $this->valueOf($keys, $project);

		return new DatabaseObject(
			connection: $driver,
			host: $this->valueOf(['database' => $keys['host'], 'databaseDefault' => '127.0.0.1'], $own),
			port: $this->valueOf(['database' => $keys['port'], 'databaseDefault' => '3306'], $own),
			database: $database,
			projectDatabase: $projectDatabase,
			isShared: ($driver !== 'sqlite' && $database === null) || ($database !== null && $database === $projectDatabase),
			sharedConnections: $driver === 'sqlite' ? [] : $this->sharedConnections($path, $own, $project),
		);
	}

	/**
	 * The other connections the migrations write to whose database is the
	 * same as the project's in these env files.
	 *
	 * @param  array<string, string|null>  $own
	 * @param  array<string, string|null>  $project
	 * @return list<string>
	 */
	private function sharedConnections(string $path, array $own, array $project): array
	{
		$connections = $this->connections($path);
		$default = $this->defaultConnection($path, $own);

		return array_values(array_filter(
			$this->migratedConnections($path),
			fn (string $connection): bool => $connection !== $default
				&& isset($connections[$connection])
				&& in_array($connections[$connection]['driver'], ['mysql', 'mariadb'], true)
				&& $this->valueOf($connections[$connection], $own) === $this->valueOf($connections[$connection], $project),
		));
	}

	/**
	 * The env keys of the default connection, as the config of the working
	 * copy names them, else Laravel's own.
	 *
	 * @param  array<string, string|null>  $environment
	 * @return array<string, string|null>
	 */
	private function defaultKeys(string $path, array $environment): array
	{
		$keys = $this->connections($path)[$this->defaultConnection($path, $environment)] ?? null;

		return $keys ?? [...self::STANDARD_KEYS, 'driver' => $environment['DB_CONNECTION'] ?? 'sqlite'];
	}

	/**
	 * The connection the app uses unless it names another: what the config's
	 * default reads from the env file, or its fallback.
	 *
	 * @param  array<string, string|null>  $environment
	 */
	private function defaultConnection(string $path, array $environment): string
	{
		$config = $this->configText($path);

		if (preg_match("/'default'\s*=>\s*env\(\s*'([A-Z0-9_]+)'\s*(?:,\s*'([^']*)')?/", $config, $found) === 1) {
			return (string) ($environment[$found[1]] ?? $found[2] ?? 'mysql');
		}

		if (preg_match("/'default'\s*=>\s*'([^']+)'/", $config, $found) === 1) {
			return $found[1];
		}

		return (string) ($environment['DB_CONNECTION'] ?? 'sqlite');
	}

	/**
	 * Per connection in config/database.php: its driver and the env keys of
	 * its database, server and account, with the fallback the config gives.
	 *
	 * @return array<string, array<string, string|null>>
	 */
	private function connections(string $path): array
	{
		if (isset($this->connectionsByPath[$path])) {
			return $this->connectionsByPath[$path];
		}

		$config = $this->configText($path);
		$start = strpos($config, "'connections'");
		$connections = [];

		if ($start !== false) {
			preg_match_all("/'([A-Za-z0-9_]+)'\s*=>\s*\[(.*?)\n\s*\],/s", substr($config, $start + strlen("'connections'")), $blocks, PREG_SET_ORDER);

			foreach ($blocks as [, $name, $body]) {
				if (preg_match("/'driver'\s*=>\s*'([a-z]+)'/", $body, $driver) !== 1) {
					continue;
				}

				[$database, $databaseDefault] = $this->envKey($body, 'database');

				$connections[$name] = [
					'driver' => $driver[1],
					'database' => $database,
					'databaseDefault' => $databaseDefault,
					'host' => $this->envKey($body, 'host')[0] ?? 'DB_HOST',
					'port' => $this->envKey($body, 'port')[0] ?? 'DB_PORT',
					'username' => $this->envKey($body, 'username')[0] ?? 'DB_USERNAME',
					'password' => $this->envKey($body, 'password')[0] ?? 'DB_PASSWORD',
				];
			}
		}

		return $this->connectionsByPath[$path] = $connections;
	}

	/**
	 * The env key a setting of a connection reads, and the fallback it gives.
	 *
	 * @return array{0: ?string, 1: ?string}
	 */
	private function envKey(string $body, string $setting): array
	{
		return preg_match("/'" . $setting . "'\s*=>\s*env\(\s*'([A-Z0-9_]+)'\s*(?:,\s*'([^']*)')?/", $body, $found) === 1
			? [$found[1], $found[2] ?? null]
			: [null, null];
	}

	/**
	 * The connections the migrations of the working copy name, besides the default one.
	 *
	 * @return list<string>
	 */
	private function migratedConnections(string $path): array
	{
		if (isset($this->migratedByPath[$path])) {
			return $this->migratedByPath[$path];
		}

		$names = [];

		foreach (glob($path . '/database/migrations/*.php') ?: [] as $file) {
			preg_match_all("/(?:connection\(\s*|\\\$connection\s*=\s*)'([A-Za-z0-9_]+)'/", (string) file_get_contents($file), $found);
			$names = [...$names, ...$found[1]];
		}

		return $this->migratedByPath[$path] = array_values(array_unique($names));
	}

	/**
	 * What a setting comes to in an env file: its value there, else the fallback of the config.
	 *
	 * @param  array<string, string|null>  $keys
	 * @param  array<string, string|null>  $environment
	 */
	private function valueOf(array $keys, array $environment): ?string
	{
		$value = $keys['database'] === null ? null : ($environment[$keys['database']] ?? null);

		return filled($value) ? (string) $value : $keys['databaseDefault'];
	}

	private function configText(string $path): string
	{
		$file = $path . '/config/database.php';

		return is_file($file) ? (string) file_get_contents($file) : '';
	}

	/**
	 * The database of a task: the project's with the last part of the branch behind it,
	 * so feature/CSM-3554 becomes test_partnerportal_csm_3554.
	 */
	private function nameFor(string $projectDatabase, string $branch): string
	{
		$suffix = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(basename($branch))), '_');

		return substr($projectDatabase . '_' . $suffix, 0, 64);
	}

	/**
	 * Creates a database when it is not there, and gives an empty one the
	 * tables of the project's; a project database that does not exist leaves
	 * it empty for the migrations.
	 */
	private function createAndFill(PDO $server, string $from, string $name): void
	{
		$server->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '`');

		$projectExists = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
		$projectExists->execute([$from]);

		if ((int) $projectExists->fetchColumn() > 0 && $this->tableCount($server, $name) === 0) {
			$this->copyTables($server, $from, $name);
		}
	}

	private function tableCount(PDO $server, string $database): int
	{
		$count = $server->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
		$count->execute([$database]);

		return (int) $count->fetchColumn();
	}

	/**
	 * Points one env key in one env file of the working copy at a database.
	 */
	private function pointAt(string $path, string $envFile, string $key, string $name): void
	{
		$file = $path . '/' . $envFile;
		$contents = is_file($file) ? (string) file_get_contents($file) : '';
		$line = $key . '=' . $name;
		$active = '/^\s*' . preg_quote($key, '/') . '=.*$/m';
		$commented = '/^#\s*' . preg_quote($key, '/') . '=.*$/m';

		// every active line counts, since the last one wins; a commented one only comes back when there is none
		$contents = match (true) {
			preg_match($active, $contents) === 1 => (string) preg_replace($active, $line, $contents),
			preg_match($commented, $contents) === 1 => (string) preg_replace($commented, $line, $contents, 1),
			default => rtrim($contents, "\n") . "\n" . $line . "\n",
		};

		file_put_contents($file, $contents);
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

		// CREATE TABLE ... LIKE leaves the foreign keys behind; they come along once every table stands
		foreach ($tables as $table) {
			foreach ($this->foreignKeysOf($server, $from, $table, $to) as $foreignKey) {
				$server->exec(sprintf('ALTER TABLE `%s`.`%s` ADD %s', $to, $table, $foreignKey));
			}
		}

		$server->prepare('SET SESSION sql_mode = ?')->execute([$sqlMode]);
		$server->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	/**
	 * The foreign key clauses of a table as the server writes them, pointed at
	 * the tables of the copy instead of the original.
	 *
	 * @return list<string>
	 */
	private function foreignKeysOf(PDO $server, string $from, string $table, string $to): array
	{
		$definition = (string) $server->query(sprintf('SHOW CREATE TABLE `%s`.`%s`', $from, $table))->fetchColumn(1);

		preg_match_all('/^\s*(CONSTRAINT `[^`]+` FOREIGN KEY .+?),?$/m', $definition, $matches);

		// a reference without a database names a table next to it; in the copy that is a table of the copy
		return array_map(
			fn (string $foreignKey): string => (string) preg_replace('/REFERENCES `([^`]+)` \(/', 'REFERENCES `' . $to . '`.`$1` (', $foreignKey),
			$matches[1],
		);
	}

	/**
	 * A connection to the server of one connection. Its account is its own;
	 * a connection that leaves its user name empty works with the account of
	 * the default connection, and else with Laravel's own keys.
	 *
	 * @param  array<string, string|null>  $environment
	 * @param  array<string, string|null>  $keys
	 * @param  array<string, string|null>  $fallbackKeys
	 */
	private function server(array $environment, array $keys, array $fallbackKeys = []): PDO
	{
		$filled = fn (?string $key): ?string => $key !== null && filled($environment[$key] ?? null) ? (string) $environment[$key] : null;

		$host = $filled($keys['host'] ?? null) ?? $filled($fallbackKeys['host'] ?? null) ?? $filled('DB_HOST') ?? '127.0.0.1';
		$port = $filled($keys['port'] ?? null) ?? $filled($fallbackKeys['port'] ?? null) ?? $filled('DB_PORT') ?? '3306';

		// user name and password belong together: the account is taken whole from the first place that names a user
		$account = collect([
			[$keys['username'] ?? null, $keys['password'] ?? null],
			[$fallbackKeys['username'] ?? null, $fallbackKeys['password'] ?? null],
			['DB_USERNAME', 'DB_PASSWORD'],
		])->first(fn (array $accountKeys): bool => $filled($accountKeys[0]) !== null);

		$username = $account === null ? 'root' : (string) $filled($account[0]);
		$password = $account === null || $account[1] === null ? '' : (string) ($environment[$account[1]] ?? '');

		return new PDO(sprintf('mysql:host=%s;port=%s', $host, $port), $username, $password, [
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
		$file = blank($database) || str_contains((string) $database, 'database_path') ? $path . '/database/database.sqlite' : (string) $database;

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
