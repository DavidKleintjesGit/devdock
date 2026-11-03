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
}
