<?php

declare(strict_types=1);

namespace Src\Prompt\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Prompt\Contracts\TailwindSetupRepositoryContract;
use Src\Prompt\Objects\TailwindSetupObject;

/**
 * Reads the Tailwind config of a project with Node, the way Tailwind itself
 * loads it. Only what survives as JSON comes back: a theme value written as a
 * function is left out.
 */
class TailwindSetupRepository implements TailwindSetupRepositoryContract
{
	private const CONFIG_FILES = ['tailwind.config.js', 'tailwind.config.cjs'];

	/**
	 * The official plugins a sketch can load as well.
	 */
	private const PLUGINS = ['forms', 'typography', 'aspect-ratio', 'line-clamp', 'container-queries'];

	public function getFor(string $directory): ?TailwindSetupObject
	{
		$config = collect(self::CONFIG_FILES)
			->map(fn (string $file): string => rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $file)
			->first(fn (string $path): bool => is_file($path));

		if ($config === null) {
			return null;
		}

		// the theme only changes when the config does
		$setupArray = Cache::rememberForever('tailwind-setup.' . md5($config . filemtime($config)), fn (): array => [
			'theme' => $this->theme($directory, $config),
			'plugins' => $this->plugins($directory),
		]);

		return new TailwindSetupObject(
			theme: (array) $setupArray['theme'],
			plugins: array_values((array) $setupArray['plugins']),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function theme(string $directory, string $config): array
	{
		$script = 'const c = require(process.argv[1]); const t = (c && c.default) || c || {}; process.stdout.write(JSON.stringify(t.theme || {}));';

		$result = Process::path($directory)->timeout(15)->run(['node', '-e', $script, $config]);

		return $result->successful() ? (array) json_decode($result->output(), true) : [];
	}

	/**
	 * @return list<string>
	 */
	private function plugins(string $directory): array
	{
		$package = json_decode((string) @file_get_contents($directory . DIRECTORY_SEPARATOR . 'package.json'), true);
		$installed = array_keys([...($package['dependencies'] ?? []), ...($package['devDependencies'] ?? [])]);

		return array_values(array_filter(
			self::PLUGINS,
			fn (string $plugin): bool => in_array('@tailwindcss/' . $plugin, $installed, true),
		));
	}
}
