<?php

declare(strict_types=1);

namespace Src\Packages\Process;

use Dotenv\Dotenv;

/**
 * The environment for a process that runs in another project. Laravel writes
 * DevDock's own .env into the process environment, and a project started from
 * here would read DB_CONNECTION and APP_ENV as its own; every one of those
 * settings is unset.
 */
class ProjectEnvironment
{
	/**
	 * @return array<string, false>
	 */
	public static function withoutDevDock(): array
	{
		return array_fill_keys(array_keys(Dotenv::parse((string) @file_get_contents(base_path('.env')))), false);
	}

	/**
	 * The search path with the folder of this PHP binary in front, so a plain
	 * php in the process, an agent's or a test run's, is that PHP.
	 *
	 * @return array<string, string>
	 */
	public static function withPhpFirst(string $phpBinary): array
	{
		// Windows spells it Path; a second PATH next to it would leave it to chance which one wins
		$name = collect(array_keys(getenv()))->first(fn (string $key): bool => strtoupper($key) === 'PATH') ?? 'PATH';

		return [$name => dirname($phpBinary) . PATH_SEPARATOR . (string) getenv($name)];
	}
}
