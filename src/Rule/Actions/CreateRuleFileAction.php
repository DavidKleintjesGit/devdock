<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Illuminate\Support\Facades\File;
use Src\Packages\Cqrs\Command;

/**
 * Puts a new rule file down, in a project or in ~/.claude. A file that is
 * already there is left alone. Returns whether it was written.
 *
 * @method static bool execute(string $path, string $contents)
 */
class CreateRuleFileAction extends Command
{
	public function __construct(
		public readonly string $path,
		public readonly string $contents,
	) {}

	public function handle(): bool
	{
		if (file_exists($this->path)) {
			return false;
		}

		File::ensureDirectoryExists(dirname($this->path));
		file_put_contents($this->path, $this->contents);

		ScanRulesAction::execute();

		return true;
	}
}
