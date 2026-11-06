<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Enums\ContextAudienceEnum;
use Src\Review\Objects\ContextFileObject;

/**
 * Lays the context of an agent or a lens out as folders: the working copy,
 * the checkout around it, your own folder, and what DevDock adds without a file.
 *
 * @method static array execute(array $entries, ?string $projectPath, ?string $workingPath)
 */
class BuildContextTreeAction extends Command
{
	/**
	 * @param  list<array{file: ContextFileObject, audience: ContextAudienceEnum}>  $entries
	 */
	public function __construct(
		public readonly array $entries,
		public readonly ?string $projectPath,
		public readonly ?string $workingPath,
	) {}

	/**
	 * @return list<array{label: string, path: ?string, nodes: list<array<string, mixed>>, count: int}>
	 */
	public function handle(): array
	{
		$home = str_replace('\\', '/', (string) (getenv('USERPROFILE') ?: getenv('HOME'))) . '/.claude/';
		$roots = [
			'working' => ['label' => __('Werkkopie'), 'path' => $this->workingPath, 'files' => []],
			'checkout' => ['label' => __('Checkout eromheen'), 'path' => $this->projectPath, 'files' => []],
			'home' => ['label' => __('Jouw map'), 'path' => '~/.claude', 'files' => []],
			'devdock' => ['label' => __('Van DevDock, geen bestand'), 'path' => null, 'files' => []],
		];

		foreach ($this->entries as $entry) {
			[$root, $relative] = $this->placeOf($entry['file'], $home);
			$roots[$root]['files'][] = [...$entry, 'relative' => $relative];
		}

		return collect($roots)
			->filter(fn (array $root) => $root['files'] !== [])
			->map(fn (array $root) => [
				'label' => $root['label'],
				'path' => $root['path'],
				'nodes' => $this->compact($this->nest($root['files'])),
				'count' => count($root['files']),
			])
			->values()
			->all();
	}

	/**
	 * The root a piece of context belongs to, and its path inside that root.
	 *
	 * @return array{0: string, 1: ?string}
	 */
	private function placeOf(ContextFileObject $contextFileObject, string $home): array
	{
		$path = $contextFileObject->path === null ? null : str_replace('\\', '/', $contextFileObject->path);
		$working = $this->workingPath === null ? null : rtrim(str_replace('\\', '/', $this->workingPath), '/') . '/';
		$project = $this->projectPath === null ? null : rtrim(str_replace('\\', '/', $this->projectPath), '/') . '/';

		return match (true) {
			$path === null => ['devdock', null],
			str_starts_with($contextFileObject->key, 'global:') => ['home', substr($path, strlen('~/.claude/'))],
			str_starts_with($contextFileObject->key, 'checkout:') => ['checkout', $path],
			$working !== null && str_starts_with(strtolower($path), strtolower($working)) => ['working', substr($path, strlen($working))],
			$project !== null && str_starts_with(strtolower($path), strtolower($project)) => ['checkout', substr($path, strlen($project))],
			str_starts_with(strtolower($path), strtolower($home)) => ['home', substr($path, strlen($home))],
			preg_match('/^[a-z]:\//i', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '~') => ['devdock', $path],
			default => ['working', $path],
		};
	}

	/**
	 * Folders and files from the paths, folders first, both in alphabetical order.
	 *
	 * @param  list<array<string, mixed>>  $files
	 * @return list<array<string, mixed>>
	 */
	private function nest(array $files): array
	{
		$folders = [];
		$leaves = [];

		foreach ($files as $entry) {
			$segments = $entry['relative'] === null ? [] : explode('/', $entry['relative']);

			if (count($segments) > 1) {
				$folders[array_shift($segments)][] = [...$entry, 'relative' => implode('/', $segments)];

				continue;
			}

			$leaves[] = [
				'type' => 'file',
				'name' => $segments[0] ?? $entry['file']->name,
				'file' => $entry['file'],
				'audience' => $entry['audience'],
			];
		}

		ksort($folders);

		return [
			...collect($folders)->map(fn (array $children, string $name) => [
				'type' => 'folder',
				'name' => $name,
				'children' => $this->nest($children),
				'count' => count($children),
			])->values()->all(),
			...$leaves,
		];
	}

	/**
	 * Joins a folder that holds only one folder with that folder, the way an editor shows a long path.
	 *
	 * @param  list<array<string, mixed>>  $nodes
	 * @return list<array<string, mixed>>
	 */
	private function compact(array $nodes): array
	{
		return collect($nodes)->map(function (array $node): array {
			if ($node['type'] !== 'folder') {
				return $node;
			}

			while (count($node['children']) === 1 && $node['children'][0]['type'] === 'folder') {
				$node = [...$node, 'name' => $node['name'] . '/' . $node['children'][0]['name'], 'children' => $node['children'][0]['children']];
			}

			return [...$node, 'children' => $this->compact($node['children'])];
		})->all();
	}
}
