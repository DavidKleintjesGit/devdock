<?php

declare(strict_types=1);

namespace Src\Skill\Repositories;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Project\Objects\ProjectEntityCollection;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Enums\SkillScopeEnum;
use Src\Skill\Objects\SkillEntity;
use Src\Skill\Objects\SkillObject;
use Src\Skill\Objects\SkillObjectCollection;

class SkillFileSystemRepository implements SkillFileSystemRepositoryContract
{
	public function getAll(ProjectEntityCollection $projectEntities): SkillObjectCollection
	{
		$skillObjects = [
			...$this->globalSkills(),
			...$this->projectSkills($projectEntities),
			...$this->pluginSkills(),
		];

		return SkillObjectCollection::fromArray($skillObjects);
	}

	public function getContents(SkillEntity $skillEntity): ?string
	{
		return is_file($skillEntity->path) ? (string) file_get_contents($skillEntity->path) : null;
	}

	public function fileCountInPlace(SkillEntity $skillEntity, string $projectPath): int
	{
		$destination = $projectPath . '/.claude/skills/' . $skillEntity->directoryName();

		return is_dir($destination) ? count(glob($destination . '/*') ?: []) : 0;
	}

	public function copyToProject(SkillEntity $skillEntity, string $projectPath): bool
	{
		$source = dirname($skillEntity->path);
		$destination = $projectPath . '/.claude/skills/' . $skillEntity->directoryName();

		return is_dir($source) && $this->copyDirectory($source, $destination);
	}

	public function createSkill(string $name, string $description, ?string $projectPath): ?string
	{
		$root = $projectPath === null
			? $this->claudeHome() . '/skills'
			: $projectPath . '/.claude/skills';

		$directory = $root . '/' . $name;

		if (is_dir($directory)) {
			return null;
		}

		if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
			return null;
		}

		$path = $directory . '/SKILL.md';
		file_put_contents($path, $this->template($name, $description));

		return $path;
	}

	private function claudeHome(): string
	{
		return dirname((string) config('devdock.paths.sessions'));
	}

	/**
	 * @return list<SkillObject>
	 */
	private function globalSkills(): array
	{
		return array_map(
			fn (array $skill) => SkillObject::from($skill + [
				'scope' => SkillScopeEnum::GLOBALLY->value,
				'project' => null,
				'plugin' => null,
			]),
			$this->skillsIn($this->claudeHome() . '/skills'),
		);
	}

	/**
	 * @return list<SkillObject>
	 */
	private function projectSkills(ProjectEntityCollection $projectEntities): array
	{
		$found = [];

		foreach ($projectEntities as $projectEntity) {
			foreach ($this->skillsIn($projectEntity->path . '/.claude/skills') as $skill) {
				$found[] = SkillObject::from($skill + [
					'scope' => SkillScopeEnum::PROJECT->value,
					'project' => $projectEntity->name,
					'plugin' => null,
				]);
			}
		}

		return $found;
	}

	/**
	 * The install list is the source: only plugins that are really there, and
	 * per plugin only the version that is installed — the cache holds on to
	 * older copies that Claude Code does not use.
	 *
	 * @return list<SkillObject>
	 */
	private function pluginSkills(): array
	{
		$file = $this->claudeHome() . '/plugins/installed_plugins.json';

		if (! is_file($file)) {
			return [];
		}

		$installed = json_decode((string) file_get_contents($file), true)['plugins'] ?? [];
		$found = [];

		foreach ($installed as $key => $installs) {
			$plugin = explode('@', $key)[0];

			foreach ($installs as $install) {
				$installPath = str_replace(DIRECTORY_SEPARATOR, '/', $install['installPath'] ?? '');

				foreach ($this->skillsIn($installPath . '/skills') as $skill) {
					$found[] = SkillObject::from([
						'name' => $plugin . ':' . $skill['name'],
						'description' => $skill['description'],
						'path' => $skill['path'],
						'lines' => $skill['lines'],
						'scope' => SkillScopeEnum::PLUGIN->value,
						'project' => null,
						'plugin' => $plugin,
					]);
				}
			}
		}

		return $found;
	}

	/**
	 * Every folder with a SKILL.md directly in it; deeper folders are reference
	 * material of a skill or an older version, not a skill of their own.
	 *
	 * @return list<array<string, int|string|null>>
	 */
	private function skillsIn(string $directory): array
	{
		if (! is_dir($directory)) {
			return [];
		}

		$found = [];

		foreach (glob($directory . '/*/SKILL.md') ?: [] as $path) {
			$contents = (string) file_get_contents($path);
			$matter = $this->frontMatter($contents);

			$found[] = [
				'name' => $matter['name'] ?? basename(dirname($path)),
				'description' => $matter['description'] ?? null,
				'path' => str_replace(DIRECTORY_SEPARATOR, '/', $path),
				'lines' => substr_count($contents, "\n") + 1,
			];
		}

		return $found;
	}

	/**
	 * The name and description lines between the dashes at the top of the file.
	 * A value may also be a yaml block: `description: >-` with the text indented
	 * on the lines below.
	 *
	 * @return array<string, string>
	 */
	private function frontMatter(string $contents): array
	{
		if (! preg_match('/^---\r?\n(.*?)\r?\n---/s', $contents, $block)) {
			return [];
		}

		$lines = explode("\n", str_replace("\r", '', $block[1]));
		$matter = [];
		$key = null;
		$folded = [];

		foreach ($lines as $line) {
			if (preg_match('/^([a-z-]+):\s*(.*)$/iu', $line, $pair)) {
				$matter[$key] = $this->joined($matter[$key] ?? null, $folded);
				$folded = [];
				$key = strtolower($pair[1]);
				$matter[$key] = in_array(trim($pair[2]), ['>', '>-', '|', '|-'], true)
					? null
					: trim($pair[2], " \t\"'");

				continue;
			}

			if ($key !== null && trim($line) !== '') {
				$folded[] = trim($line);
			}
		}

		$matter[$key] = $this->joined($matter[$key] ?? null, $folded);

		return array_filter(array_intersect_key($matter, array_flip(['name', 'description'])));
	}

	private function joined(?string $value, array $folded): ?string
	{
		return $folded === [] ? $value : trim($value . ' ' . implode(' ', $folded));
	}

	private function template(string $name, string $description): string
	{
		$title = ucfirst(str_replace('-', ' ', $name));

		return <<<MARKDOWN
			---
			name: {$name}
			description: {$description}
			---

			# {$title}

			MARKDOWN;
	}

	private function copyDirectory(string $source, string $destination): bool
	{
		if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
		);

		foreach ($iterator as $item) {
			$inside = substr($item->getPathname(), strlen($source) + 1);
			$path = $destination . DIRECTORY_SEPARATOR . $inside;

			if ($item->isDir()) {
				if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
					return false;
				}

				continue;
			}

			if (! copy($item->getPathname(), $path)) {
				return false;
			}
		}

		return true;
	}
}
