<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Objects\LensEntity;

/**
 * Looks at a project the way its lenses would: which of their paths exist
 * there, whether their command can run, and which rule files they read. A
 * lens that finds nothing to look at does not take part.
 *
 * @method static array{stack: ?string, ruleFiles: array<int, string>, ruleFolders: array<int, string>, lenses: array<int, array<string, mixed>>, broken: int, warned: int} execute(string $project)
 */
class CheckWorkflowAction extends Command
{
	/**
	 * Where a project keeps the rules an agent reads.
	 */
	private const RULE_FOLDERS = ['.claude/rules', '.cursor/rules'];

	private const RULE_FILES = ['CLAUDE.md', 'CLAUDE.local.md', 'AGENTS.md', '.design/DESIGN-SYSTEM.md'];

	/**
	 * What a lens reads when nothing in the project fits its patterns.
	 */
	private const FALLBACK_RULES = ['CLAUDE.md', 'AGENTS.md'];

	public function __construct(
		public readonly string $project,
	) {}

	/**
	 * @return array{stack: ?string, ruleFiles: array<int, string>, ruleFolders: array<int, string>, lenses: array<int, array<string, mixed>>, broken: int, warned: int}
	 */
	public function handle(LensRepositoryContract $lensRepository): array
	{
		$projectEntity = GetProjectsAction::execute()
			->first(fn (ProjectEntity $projectEntity) => $projectEntity->name === $this->project);

		if ($projectEntity === null) {
			return ['stack' => null, 'ruleFiles' => [], 'ruleFolders' => [], 'lenses' => [], 'broken' => 0, 'warned' => 0];
		}

		$root = rtrim(str_replace('\\', '/', $projectEntity->path), '/');
		$ruleFiles = $this->ruleFilesOf($root);

		$lenses = collect($lensRepository->getAll($this->project))
			->filter(fn (LensEntity $lensEntity) => $lensEntity->isEnabled)
			->map(fn (LensEntity $lensEntity) => $this->check($lensEntity, $root, $ruleFiles))
			->values()
			->all();

		return [
			'stack' => $this->stackOf($root),
			'ruleFiles' => $ruleFiles,
			'ruleFolders' => array_values(array_filter(
				[...self::RULE_FOLDERS, ...self::RULE_FILES],
				fn (string $path) => file_exists($root . '/' . $path),
			)),
			'lenses' => $lenses,
			'broken' => count(array_filter($lenses, fn (array $lens) => $lens['state'] === 'off')),
			'warned' => count(array_filter($lenses, fn (array $lens) => $lens['state'] === 'warn')),
		];
	}

	/**
	 * @param  array<int, string>  $ruleFiles
	 * @return array<string, mixed>
	 */
	private function check(LensEntity $lensEntity, string $root, array $ruleFiles): array
	{
		$paths = array_map(fn (string $pattern) => [
			'pattern' => $pattern,
			'count' => $this->exists($root, $pattern) ? 1 : 0,
		], $lensEntity->paths);

		$reads = $lensEntity->hasPickedRules
			? array_values(array_filter($lensEntity->rules, fn (string $file) => is_file($root . '/' . $file)))
			: $this->matching($lensEntity->rules, $ruleFiles);

		$fallback = $reads === []
			? array_values(array_filter(self::FALLBACK_RULES, fn (string $file) => is_file($root . '/' . $file)))
			: [];

		$commandWorks = $this->commandWorks($lensEntity->command, $root);
		$found = array_filter($paths, fn (array $path) => $path['count'] > 0);

		// a path that does not exist here is skipped; only a lens with none
		// left, or without its own rules, needs a look
		$state = match (true) {
			($paths !== [] && $found === []) || ! $commandWorks => 'off',
			$fallback !== [] || $reads === [] => 'warn',
			default => 'ok',
		};

		return [
			'lens' => $lensEntity,
			'paths' => $paths,
			'reads' => $reads === [] ? $fallback : $reads,
			'isFallback' => $reads === [],
			'commandWorks' => $commandWorks,
			'state' => $state,
		];
	}

	/**
	 * @param  array<int, string>  $patterns
	 * @param  array<int, string>  $ruleFiles
	 * @return array<int, string>
	 */
	private function matching(array $patterns, array $ruleFiles): array
	{
		return array_values(array_filter($ruleFiles, function (string $file) use ($patterns): bool {
			foreach ($patterns as $pattern) {
				if (fnmatch($pattern, $file, FNM_NOESCAPE) || fnmatch($pattern, '/' . $file, FNM_NOESCAPE)) {
					return true;
				}
			}

			return false;
		}));
	}

	/**
	 * Whether anything can fall under a pattern here: the part before the
	 * first wildcard has to exist. Listing every file of every project would
	 * take longer than a page may.
	 */
	private function exists(string $root, string $pattern): bool
	{
		$prefix = rtrim(substr($pattern, 0, strcspn($pattern, '*?[')), '/');

		return $prefix === '' || file_exists($root . '/' . $prefix);
	}

	/**
	 * Whether the first word of the command exists in this project: artisan,
	 * a binary under vendor or node_modules, or an npm script.
	 */
	private function commandWorks(?string $command, string $root): bool
	{
		if (blank($command)) {
			return true;
		}

		$words = preg_split('/\s+/', trim((string) $command)) ?: [];

		return match (true) {
			($words[0] ?? '') === 'php' && ($words[1] ?? '') === 'artisan' => is_file($root . '/artisan'),
			str_starts_with($words[0] ?? '', 'vendor/') || str_starts_with($words[0] ?? '', 'node_modules/') => is_file($root . '/' . $words[0]),
			in_array($words[0] ?? '', ['npm', 'pnpm', 'yarn'], true) => is_file($root . '/package.json'),
			default => true,
		};
	}

	/**
	 * @return array<int, string>
	 */
	private function ruleFilesOf(string $root): array
	{
		$found = [];

		foreach (self::RULE_FOLDERS as $folder) {
			if (! is_dir($root . '/' . $folder)) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS));

			foreach ($iterator as $file) {
				$extension = strtolower($file->getExtension());

				// DevDock's own context files stand there too, but are not rules
				if ($file->isFile() && in_array($extension, ['md', 'mdc'], true) && ! str_starts_with($file->getFilename(), 'devdock-')) {
					$found[] = $folder . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/' . $folder) + 1));
				}
			}
		}

		foreach (self::RULE_FILES as $file) {
			if (is_file($root . '/' . $file)) {
				$found[] = $file;
			}
		}

		sort($found);

		return $found;
	}

	/**
	 * What the project is built with, in a few words.
	 */
	private function stackOf(string $root): ?string
	{
		$composer = is_file($root . '/composer.json') ? (string) file_get_contents($root . '/composer.json') : '';
		$package = is_file($root . '/package.json') ? (string) file_get_contents($root . '/package.json') : '';

		return match (true) {
			str_contains($composer, 'laravel/framework') => 'Laravel',
			str_contains($package, '"next"') && str_contains($package, 'payload') => 'Next.js met Payload',
			str_contains($package, '"next"') => 'Next.js',
			$package !== '' => 'Node',
			$composer !== '' => 'PHP',
			default => null,
		};
	}
}
