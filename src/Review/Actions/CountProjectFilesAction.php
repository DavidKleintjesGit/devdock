<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;

/**
 * How many files git knows in a project under a pattern, for showing what a
 * path of a lens would take in.
 *
 * @method static int execute(string $project, string $pattern)
 */
class CountProjectFilesAction extends Command
{
	public function __construct(
		public readonly string $project,
		public readonly string $pattern,
	) {}

	public function handle(): int
	{
		$path = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project)
			?->path;

		if ($path === null) {
			return 0;
		}

		$files = Cache::remember('workflow.files.' . md5($path), 300, function () use ($path): array {
			$result = Process::path($path)->run(['git', 'ls-files']);

			return $result->successful()
				? array_values(array_filter(preg_split('/\R/', trim($result->output())) ?: []))
				: [];
		});

		return count(array_filter($files, fn (string $file) => fnmatch($this->pattern, $file, FNM_NOESCAPE)));
	}
}
