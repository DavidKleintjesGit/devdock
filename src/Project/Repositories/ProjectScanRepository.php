<?php

declare(strict_types=1);

namespace Src\Project\Repositories;

use Illuminate\Support\Facades\Process;
use Src\Project\Contracts\ProjectScanRepositoryContract;
use Src\Project\Objects\ProjectObject;
use Src\Project\Objects\ProjectObjectCollection;

class ProjectScanRepository implements ProjectScanRepositoryContract
{
	public function getAll(string $sitesPath): ProjectObjectCollection
	{
		if (! is_dir($sitesPath)) {
			return ProjectObjectCollection::fromArray([]);
		}

		$projectObjects = [];

		foreach ($this->repositoryPaths($sitesPath) as $name => $path) {
			$projectObjects[] = ProjectObject::from($this->stateOf($path) + [
				'name' => $name,
				'path' => $path,
			]);
		}

		return ProjectObjectCollection::fromArray($projectObjects);
	}

	/**
	 * @return array<string, string>
	 */
	private function repositoryPaths(string $sitesPath): array
	{
		$paths = [];

		foreach (scandir($sitesPath) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $sitesPath . '/' . $entry;

			if (is_dir($path . '/.git')) {
				$paths[$entry] = $path;
			}
		}

		ksort($paths);

		return $paths;
	}

	/**
	 * The branch, the standing against origin and the number of changed files.
	 *
	 * One git call delivers all three. With four separate calls per project, a
	 * round over 37 repositories took half a minute.
	 *
	 * @return array<string, int|string|null>
	 */
	private function stateOf(string $path): array
	{
		$result = Process::path($path)->run('git status --porcelain=v2 --branch');

		if (! $result->successful()) {
			return [
				'branch' => null,
				'remote' => $this->remoteOf($path),
				'changed_files' => 0,
				'ahead' => 0,
				'behind' => 0,
			];
		}

		$branch = null;
		$ahead = 0;
		$behind = 0;
		$changedFiles = 0;

		foreach (explode("\n", $result->output()) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (! str_starts_with($line, '#')) {
				$changedFiles++;

				continue;
			}

			if (str_starts_with($line, '# branch.head ')) {
				$head = substr($line, 14);
				$branch = $head === '(detached)' ? null : $head;
			}

			if (str_starts_with($line, '# branch.ab ') && preg_match('/\+(\d+) -(\d+)/', $line, $counts)) {
				$ahead = (int) $counts[1];
				$behind = (int) $counts[2];
			}
		}

		return [
			'branch' => $branch,
			'remote' => $this->remoteOf($path),
			'changed_files' => $changedFiles,
			'ahead' => $ahead,
			'behind' => $behind,
		];
	}

	/**
	 * The remote from .git/config; that saves another process per project.
	 */
	private function remoteOf(string $path): ?string
	{
		$config = $path . '/.git/config';

		if (! is_file($config)) {
			return null;
		}

		preg_match('/\[remote "origin"\][^\[]*url\s*=\s*(.+)/', (string) file_get_contents($config), $found);

		return isset($found[1]) ? trim($found[1]) : null;
	}
}
