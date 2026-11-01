<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;

/**
 * Your own rules in ~/.claude, which every agent reads in every project, and
 * the mode Claude Code starts in.
 *
 * @method static array{folder: string, files: array<string, string>, defaultMode: ?string} execute()
 */
class GetGlobalRulesAction extends Command
{
	/**
	 * @return array{folder: string, files: array<string, string>, defaultMode: ?string}
	 */
	public function handle(): array
	{
		$folder = self::folder();
		$files = [];

		if (is_file($folder . '/CLAUDE.md')) {
			$files['CLAUDE.md'] = (string) file_get_contents($folder . '/CLAUDE.md');
		}

		foreach (glob($folder . '/rules/*.md') ?: [] as $file) {
			$files['rules/' . basename($file)] = (string) file_get_contents($file);
		}

		$settings = is_file($folder . '/settings.json')
			? json_decode((string) file_get_contents($folder . '/settings.json'), true)
			: null;

		return [
			'folder' => $folder,
			'files' => $files,
			'defaultMode' => is_array($settings) ? ($settings['permissions']['defaultMode'] ?? null) : null,
		];
	}

	/**
	 * Where Claude Code keeps what is yours: ~/.claude.
	 */
	public static function folder(): string
	{
		$home = (string) (getenv('USERPROFILE') ?: getenv('HOME'));

		return rtrim(strtr($home, DIRECTORY_SEPARATOR, '/'), '/') . '/.claude';
	}
}
