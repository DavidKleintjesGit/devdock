<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Objects\ContextFileObject;
use Src\Rule\Actions\GetGlobalRulesAction;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;

/**
 * The rule files the agent and every lens get alike: your own in ~/.claude,
 * the project's, and the memory index. Without a project only your own.
 *
 * @method static array<int, ContextFileObject> execute(?string $projectPath, ?string $workingPath)
 */
class GetRuleContextFilesAction extends Command
{
	public function __construct(
		public readonly ?string $projectPath,
		// the working copy a task runs in, or the project itself
		public readonly ?string $workingPath,
	) {}

	/**
	 * @return array<int, ContextFileObject>
	 */
	public function handle(RuleFileSystemRepositoryContract $ruleFileSystemRepository): array
	{
		$files = [];

		foreach (GetGlobalRulesAction::execute()['files'] as $path => $contents) {
			$files[] = new ContextFileObject(key: 'global:' . $path, name: basename($path), group: __('Globaal'), path: '~/.claude/' . $path, contents: $contents);
		}

		$folder = $this->workingPath ?? $this->projectPath;

		if ($folder === null) {
			return $files;
		}

		// Claude Code walks up from a working copy and reads the instructions of the checkout around it too
		if ($this->projectPath !== null && $folder !== $this->projectPath && str_starts_with($folder, $this->projectPath . '/')) {
			foreach ($ruleFileSystemRepository->getLoadedFiles($this->projectPath) as $path => $contents) {
				if (! str_contains($path, '/')) {
					$files[] = new ContextFileObject(key: 'checkout:' . $path, name: $path, group: __('Uit de checkout eromheen'), path: $path, contents: $contents);
				}
			}
		}

		$projectFiles = collect($ruleFileSystemRepository->getLoadedFiles($folder))
			->map(fn (string $contents, string $path) => new ContextFileObject(
				key: 'project:' . $path,
				name: str_starts_with($path, '.claude/rules/') ? substr($path, strlen('.claude/rules/')) : $path,
				group: $path === 'CLAUDE.local.md' || str_contains($path, '/local/') ? __('Lokaal, alleen voor jou') : __('Project'),
				path: $path,
				contents: $contents,
			))
			->sortBy(fn (ContextFileObject $contextFileObject) => $contextFileObject->group === __('Project') ? 0 : 1)
			->values()
			->all();

		$memoryIndex = $this->projectPath === null ? null : $ruleFileSystemRepository->getMemoryIndex($this->projectPath);

		return [
			...$files,
			...$projectFiles,
			...($memoryIndex === null ? [] : [new ContextFileObject(key: 'memory', name: 'MEMORY.md', group: __('Geheugen'), description: __('de index; de notities zelf leest Claude als hij ze nodig heeft'), contents: $memoryIndex)]),
		];
	}
}
