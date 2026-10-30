<?php

declare(strict_types=1);

namespace Src\Rule\Repositories;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Project\Objects\ProjectEntity;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Enums\RuleGroupEnum;
use Src\Rule\Enums\RuleOriginEnum;
use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileObject;
use Src\Rule\Objects\RuleFileObjectCollection;

class RuleFileSystemRepository implements RuleFileSystemRepositoryContract
{
	/**
	 * The source files in mtvtd-core, by file name.
	 *
	 * @var array<string, string>
	 */
	private array $coreFiles = [];

	public function getAll(ProjectEntityCollection $projectEntities): RuleFileObjectCollection
	{
		$this->coreFiles = $this->filesInCore();
		$ruleFileObjects = [];

		foreach ($projectEntities as $projectEntity) {
			foreach ($this->pathsIn($projectEntity) as $path => $group) {
				$ruleFileObjects[] = RuleFileObject::from(
					$this->measurementsOf($projectEntity->path . '/' . $path, $path) + [
						'project' => $projectEntity->name,
						'path' => $path,
						'group' => $group->value,
					],
				);
			}

			foreach ($this->memoryOf($projectEntity) as $path => $file) {
				// de linkerkant wint bij +, en een geheugen is altijd van jou
				$ruleFileObjects[] = RuleFileObject::from([
					'project' => $projectEntity->name,
					'path' => $path,
					'summary' => $this->describedIn($file),
					'group' => RuleGroupEnum::MEMORY->value,
					'origin' => RuleOriginEnum::ONLY_YOURS->value,
				] + $this->measurementsOf($file, $path));
			}
		}

		return RuleFileObjectCollection::fromArray($ruleFileObjects);
	}

	public function getContents(RuleFileEntity $ruleFileEntity): ?string
	{
		$path = $this->fullPathOf($ruleFileEntity);

		return is_file($path) ? (string) file_get_contents($path) : null;
	}

	public function copyFromCore(RuleFileEntity $ruleFileEntity): bool
	{
		if (blank($ruleFileEntity->coreFile)) {
			return false;
		}

		$source = $this->coreDirectory() . '/' . $ruleFileEntity->coreFile;

		return $this->copy($source, $this->fullPathOf($ruleFileEntity));
	}

	public function copyToProject(RuleFileEntity $ruleFileEntity, string $projectPath): bool
	{
		return $this->copy($this->fullPathOf($ruleFileEntity), $projectPath . '/' . $ruleFileEntity->path);
	}

	private function fullPathOf(RuleFileEntity $ruleFileEntity): string
	{
		if ($ruleFileEntity->group === RuleGroupEnum::MEMORY) {
			return $this->memoryDirectoryOf($this->sitesPath() . '/' . $ruleFileEntity->project)
. '/' . basename($ruleFileEntity->path);
		}

		return $this->sitesPath() . '/' . $ruleFileEntity->project . '/' . $ruleFileEntity->path;
	}

	/**
	 * Everything Claude wrote down about a project. It steers an agent as much
	 * as the rules do, so it belongs on the same page instead of out of sight.
	 *
	 * @return array<string, string> the path on the page to the file on disk
	 */
	private function memoryOf(ProjectEntity $projectEntity): array
	{
		$directory = $this->memoryDirectoryOf($projectEntity->path);
		$found = [];

		foreach (glob($directory . '/*.md') ?: [] as $file) {
			$found['geheugen/' . basename($file)] = $file;
		}

		return $found;
	}

	/**
	 * Claude keeps a folder per project, named after its path with a dash in
	 * the place of every other character.
	 */
	private function memoryDirectoryOf(string $projectPath): string
	{
		$folder = (string) preg_replace('/[^A-Za-z0-9]/', '-', $projectPath);

		return rtrim((string) config('devdock.paths.sessions'), '/\\') . '/' . $folder . '/memory';
	}

	/**
	 * The one line a memory writes about itself in its frontmatter.
	 */
	private function describedIn(string $file): ?string
	{
		$handle = fopen($file, 'r');

		if ($handle === false) {
			return null;
		}

		$description = null;

		for ($line = 0; $line < 12 && ! feof($handle); $line++) {
			$text = (string) fgets($handle);

			if (preg_match('/^description:\s*(.+)$/i', trim($text), $found) === 1) {
				$description = trim($found[1], " \t\"'");

				break;
			}
		}

		fclose($handle);

		return $description;
	}

	private function sitesPath(): string
	{
		return (string) config('devdock.paths.sites');
	}

	private function coreDirectory(): string
	{
		return config('devdock.paths.core') . '/ai/instructions';
	}

	/**
	 * The rule files of one project, with the group they sit in on the board.
	 *
	 * @return array<string, RuleGroupEnum>
	 */
	private function pathsIn(ProjectEntity $projectEntity): array
	{
		$found = [];

		foreach (['CLAUDE.md', 'CLAUDE.local.md'] as $path) {
			if (is_file($projectEntity->path . '/' . $path)) {
				$found[$path] = RuleGroupEnum::PROJECT_INSTRUCTIONS;
			}
		}

		foreach ($this->markdownIn($projectEntity->path, '.claude/rules') as $path) {
			$found[$path] = str_contains($path, '/local/') ? RuleGroupEnum::OWN_RULES : RuleGroupEnum::SHARED_RULES;
		}

		// Windows finds the same file under both spellings; the first one counts
		foreach (['.design/DESIGN-SYSTEM.md', '.design/design-system.md'] as $path) {
			if (is_file($projectEntity->path . '/' . $path)) {
				$found[$path] = RuleGroupEnum::DESIGN;
				break;
			}
		}

		return $found;
	}

	/**
	 * Every markdown file under a folder, as a path relative to the project
	 * folder.
	 *
	 * @return list<string>
	 */
	private function markdownIn(string $root, string $relative): array
	{
		$directory = $root . '/' . $relative;

		if (! is_dir($directory)) {
			return [];
		}

		$found = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
				$inside = substr($file->getPathname(), strlen($directory) + 1);
				$found[] = $relative . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $inside);
			}
		}

		sort($found);

		return $found;
	}

	/**
	 * @return array<string, int|string|null>
	 */
	private function measurementsOf(string $fullPath, string $path): array
	{
		$contents = (string) file_get_contents($fullPath);
		$coreFile = $this->coreFiles[basename($path)] ?? null;

		return [
			'origin' => $this->originOf($path, $contents, $coreFile)->value,
			'lines' => substr_count($contents, "\n") + 1,
			'words' => str_word_count(strip_tags($contents)),
			'bytes' => strlen($contents),
			'core_file' => $coreFile === null ? null : basename($coreFile),
			'core_lines' => $coreFile === null ? null : substr_count((string) file_get_contents($coreFile), "\n") + 1,
		];
	}

	private function originOf(string $path, string $contents, ?string $coreFile): RuleOriginEnum
	{
		if (str_ends_with($path, 'CLAUDE.local.md')) {
			return RuleOriginEnum::ONLY_YOURS;
		}

		if ($coreFile === null) {
			return RuleOriginEnum::ONLY_HERE;
		}

		return trim($contents) === trim((string) file_get_contents($coreFile))
			? RuleOriginEnum::FROM_CORE
			: RuleOriginEnum::BEHIND_CORE;
	}

	/**
	 * @return array<string, string>
	 */
	private function filesInCore(): array
	{
		$found = [];

		foreach (glob($this->coreDirectory() . '/*.md') ?: [] as $path) {
			$found[basename($path)] = $path;
		}

		return $found;
	}

	private function copy(string $source, string $target): bool
	{
		if (! is_file($source)) {
			return false;
		}

		$directory = dirname($target);

		if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
			return false;
		}

		return copy($source, $target);
	}
}
