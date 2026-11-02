<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\FileTreeNodeObject;

/**
 * Lays the changed files out as a tree of folders, in the order Bitbucket
 * uses: the files of a folder first, then its folders, each alphabetically.
 *
 * @method static array execute(array $changes)
 */
class BuildFileTreeAction extends Command
{
	/**
	 * @param  array<int, ChangeObject>  $changes
	 */
	public function __construct(
		public readonly array $changes,
	) {}

	/**
	 * @return array<int, FileTreeNodeObject>
	 */
	public function handle(): array
	{
		$root = ['folders' => [], 'files' => []];

		foreach ($this->changes as $changeObject) {
			$parts = explode('/', trim(str_replace('\\', '/', $changeObject->path), '/'));
			$fileName = (string) array_pop($parts);
			$folder = &$root;

			foreach ($parts as $part) {
				$folder['folders'][$part] ??= ['folders' => [], 'files' => []];
				$folder = &$folder['folders'][$part];
			}

			$folder['files'][$fileName] = $changeObject;
			unset($folder);
		}

		return $this->nodes($root, '');
	}

	/**
	 * @param  array{folders: array<string, mixed>, files: array<string, ChangeObject>}  $folder
	 * @return array<int, FileTreeNodeObject>
	 */
	private function nodes(array $folder, string $prefix): array
	{
		ksort($folder['files'], SORT_NATURAL | SORT_FLAG_CASE);
		ksort($folder['folders'], SORT_NATURAL | SORT_FLAG_CASE);

		$nodes = [];

		foreach ($folder['files'] as $name => $changeObject) {
			$nodes[] = new FileTreeNodeObject((string) $name, $changeObject->path, $changeObject, []);
		}

		foreach ($folder['folders'] as $name => $inner) {
			$name = (string) $name;

			// a folder that only holds one folder is shown as one line: Http/Controllers/Admin
			while ($inner['files'] === [] && count($inner['folders']) === 1) {
				$next = array_key_first($inner['folders']);
				$name .= '/' . $next;
				$inner = $inner['folders'][$next];
			}

			$path = $prefix . $name;
			$nodes[] = new FileTreeNodeObject($name, $path, null, $this->nodes($inner, $path . '/'));
		}

		return $nodes;
	}
}
