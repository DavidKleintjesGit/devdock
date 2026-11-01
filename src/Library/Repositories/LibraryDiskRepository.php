<?php

declare(strict_types=1);

namespace Src\Library\Repositories;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Enums\LandingEnum;
use Src\Library\Enums\LibraryItemKindEnum;
use Src\Library\Objects\LibraryItemEntity;

class LibraryDiskRepository implements LibraryDiskRepositoryContract
{
	public function readFolder(string $folder, LibraryItemKindEnum $kind): array
	{
		if (! is_dir($folder)) {
			return [];
		}

		$found = [];

		if ($kind === LibraryItemKindEnum::RULE) {
			foreach (glob($folder . '/*.md') ?: [] as $file) {
				$found[basename($file)] = (string) file_get_contents($file);
			}

			return $found;
		}

		foreach (glob($folder . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
			if (is_file($directory . '/SKILL.md')) {
				$found[basename($directory)] = $this->packSkill($directory);
			}
		}

		return $found;
	}

	public function historyOf(string $folder, string $name): array
	{
		$log = Process::path($folder)->run(['git', 'log', '--follow', '--format=%H%x09%cI%x09%s', '--', $name]);

		if (! $log->successful() || trim($log->output()) === '') {
			return [];
		}

		$history = [];

		foreach (array_reverse(preg_split('/\R/', trim($log->output())) ?: []) as $line) {
			[$sha, $at, $subject] = array_pad(explode("\t", $line, 3), 3, '');

			// git show wants the path from the root of the repository
			$shown = Process::path($folder)->run(['git', 'show', $sha . ':./' . $name]);

			if ($shown->successful()) {
				$history[] = [
					'contents' => $shown->output(),
					'note' => $subject,
					'at' => CarbonImmutable::parse($at),
				];
			}
		}

		return $history;
	}

	public function writeFolder(string $folder, LibraryItemEntity $item, string $contents): void
	{
		if ($item->kind === LibraryItemKindEnum::SKILL) {
			$this->unpackSkill($folder . '/' . $item->name, $contents);

			return;
		}

		File::ensureDirectoryExists($folder);
		file_put_contents($folder . '/' . $item->name, $contents);
	}

	public function readProjectItem(string $projectPath, LibraryItemEntity $item, LandingEnum $landing, ?string $folder): ?string
	{
		if ($item->kind === LibraryItemKindEnum::SKILL) {
			$directory = $projectPath . '/.claude/skills/' . $item->name;

			return is_dir($directory) ? $this->packSkill($directory) : null;
		}

		$file = $projectPath . '/' . $landing->pathFor($item->name, $folder);

		if (! is_file($file)) {
			return null;
		}

		$contents = (string) file_get_contents($file);

		return match ($landing) {
			LandingEnum::AGENTS => $this->blockIn($contents, $item->slug()),
			LandingEnum::CURSOR => $this->withoutHeader($contents),
			default => $contents,
		};
	}

	public function writeProjectItem(string $projectPath, LibraryItemEntity $item, LandingEnum $landing, ?string $folder, string $contents): ?string
	{
		$before = $this->readProjectItem($projectPath, $item, $landing, $folder);

		if ($item->kind === LibraryItemKindEnum::SKILL) {
			$this->unpackSkill($projectPath . '/.claude/skills/' . $item->name, $contents);

			return $before;
		}

		$file = $projectPath . '/' . $landing->pathFor($item->name, $folder);
		File::ensureDirectoryExists(dirname($file));

		$written = match ($landing) {
			LandingEnum::AGENTS => $this->withBlock(is_file($file) ? (string) file_get_contents($file) : '', $item->slug(), $contents),
			LandingEnum::CURSOR => $this->cursorFile($item, $contents),
			default => $contents,
		};

		file_put_contents($file, $written);

		return $before;
	}

	public function landingsIn(string $projectPath): array
	{
		return [
			LandingEnum::CLAUDE->value => is_dir($projectPath . '/.claude/rules'),
			LandingEnum::CURSOR->value => is_dir($projectPath . '/.cursor/rules'),
			LandingEnum::AGENTS->value => is_file($projectPath . '/AGENTS.md'),
			LandingEnum::CUSTOM->value => false,
		];
	}

	public function projectFiles(string $projectPath, LibraryItemKindEnum $kind): array
	{
		$folder = $kind === LibraryItemKindEnum::RULE ? '/.claude/rules' : '/.claude/skills';

		return $this->readFolder($projectPath . $folder, $kind);
	}

	/**
	 * The block between the two markers of this rule in AGENTS.md.
	 */
	private function blockIn(string $contents, string $slug): ?string
	{
		$pattern = '/<!-- devdock:' . preg_quote($slug, '/') . ' begin -->\R(.*?)\R<!-- devdock:' . preg_quote($slug, '/') . ' einde -->/s';

		return preg_match($pattern, $contents, $found) === 1 ? $found[1] : null;
	}

	/**
	 * AGENTS.md with the block of this rule replaced, or added at the end.
	 */
	private function withBlock(string $contents, string $slug, string $block): string
	{
		$wrapped = '<!-- devdock:' . $slug . ' begin -->' . "\n" . rtrim($block) . "\n" . '<!-- devdock:' . $slug . ' einde -->';
		$pattern = '/<!-- devdock:' . preg_quote($slug, '/') . ' begin -->.*?<!-- devdock:' . preg_quote($slug, '/') . ' einde -->/s';

		if (preg_match($pattern, $contents) === 1) {
			return (string) preg_replace_callback($pattern, fn () => $wrapped, $contents, 1);
		}

		return ltrim(rtrim($contents) . "\n\n" . $wrapped . "\n");
	}

	/**
	 * A Cursor rule opens with a header of its own; the library keeps the
	 * text without it.
	 */
	private function withoutHeader(string $contents): string
	{
		return (string) preg_replace('/^---\R.*?\R---\R/s', '', $contents, 1);
	}

	private function cursorFile(LibraryItemEntity $item, string $contents): string
	{
		$description = trim((string) strtok(ltrim($contents, "# \n"), "\n"));

		return "---\ndescription: " . $description . "\nalwaysApply: true\n---\n" . $contents;
	}

	/**
	 * All files of a skill as one content, so a version of a skill is the
	 * whole folder at once.
	 */
	private function packSkill(string $directory): string
	{
		$files = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
				$files[$relative] = (string) file_get_contents($file->getPathname());
			}
		}

		ksort($files);

		return (string) json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	private function unpackSkill(string $directory, string $contents): void
	{
		$files = json_decode($contents, true);

		if (! is_array($files)) {
			return;
		}

		foreach ($files as $relative => $text) {
			$target = $directory . '/' . $relative;
			File::ensureDirectoryExists(dirname($target));
			file_put_contents($target, (string) $text);
		}
	}
}
