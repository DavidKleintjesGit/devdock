<?php

declare(strict_types=1);

namespace Src\Project\Repositories;

use Illuminate\Support\Facades\Process;
use Src\Packages\Process\DetachedCommand;
use Src\Project\Contracts\ProjectPhpRepositoryContract;

class ProjectPhpRepository implements ProjectPhpRepositoryContract
{
	/**
	 * Extensions that only exist on Linux; Herd on Windows cannot load them,
	 * while the packages that ask for them only need them on the server.
	 */
	private const LINUX_ONLY_EXTENSIONS = ['ext-pcntl', 'ext-posix'];

	/**
	 * @var array<string, ?string>
	 */
	private array $versions = [];

	public function binaryFor(string $path): string
	{
		$version = $this->versionFor($path);

		return $version === null ? resolve(DetachedCommand::class)->phpBinary() : $this->binaryOf($version);
	}

	public function versionFor(string $path): ?string
	{
		$requirements = $this->requirementsOf($path);

		if ($requirements === []) {
			return null;
		}

		return $this->versions[implode('&', $requirements)] ??= collect($this->installedVersions())
			->first(fn (string $version): bool => collect($requirements)->every(fn (string $requirement): bool => $this->meets($version, $requirement)));
	}

	public function composerCommand(string $path, array $arguments): array
	{
		$composer = $this->herdBin() === null ? null : $this->herdBin() . '/composer.phar';
		$ignored = PHP_OS_FAMILY === 'Windows'
			? array_map(fn (string $extension): string => '--ignore-platform-req=' . $extension, self::LINUX_ONLY_EXTENSIONS)
			: [];

		return $composer !== null && is_file($composer)
			? [$this->binaryFor($path), $composer, ...$arguments, ...$ignored]
			: ['composer', ...$arguments, ...$ignored];
	}

	public function siteVersion(string $site): ?string
	{
		$herdBin = $this->herdBin();

		if ($herdBin === null) {
			return null;
		}

		$serverBlock = (string) @file_get_contents(dirname($herdBin) . '/config/valet/Nginx/' . $site . '.conf');

		return preg_match('/ISOLATED_PHP_VERSION=(\d+\.\d+)/', $serverBlock, $parts) === 1 ? $parts[1] : $this->herdDefaultVersion();
	}

	public function isolateSite(string $path, string $site): void
	{
		$version = $this->versionFor($path);
		$herd = $this->herdBin() === null ? null : $this->herdBin() . '/herd.phar';

		if ($version === null || $herd === null || ! is_file($herd) || $version === $this->siteVersion($site)) {
			return;
		}

		// herd isolate takes the name without the top-level domain
		Process::timeout(120)->run([$this->binaryOf($version), $herd, 'isolate', '--site=' . preg_replace('/\.[a-z]+$/', '', $site), $version]);
	}

	/**
	 * The PHP requirements of the folder: the one in its composer.json, such
	 * as ~8.5.0, and those of every locked package, which can ask for more.
	 *
	 * @return list<string>
	 */
	private function requirementsOf(string $path): array
	{
		$composer = json_decode((string) @file_get_contents($path . '/composer.json'), true);
		$requirement = is_array($composer) ? ($composer['require']['php'] ?? null) : null;

		if (! is_string($requirement) || trim($requirement) === '') {
			return [];
		}

		$lock = json_decode((string) @file_get_contents($path . '/composer.lock'), true);
		$packages = is_array($lock) ? [...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] : [];

		return collect($packages)
			->map(fn (array $package): ?string => $package['require']['php'] ?? null)
			->filter(fn (?string $packageRequirement): bool => is_string($packageRequirement) && trim($packageRequirement) !== '')
			->map(fn (string $packageRequirement): string => trim($packageRequirement))
			->prepend(trim($requirement))
			->unique()
			->values()
			->all();
	}

	/**
	 * The PHP versions Herd has, lowest first, as major.minor.
	 *
	 * @return list<string>
	 */
	private function installedVersions(): array
	{
		$herdBin = $this->herdBin();

		if ($herdBin === null) {
			return [];
		}

		return collect(glob($herdBin . '/php[0-9][0-9]', GLOB_ONLYDIR) ?: [])
			->filter(fn (string $folder): bool => is_file($folder . '/php.exe') || is_file($folder . '/php'))
			->map(fn (string $folder): string => preg_replace('/^php(\d)(\d+)$/', '$1.$2', basename($folder)))
			->sort(fn (string $first, string $second): int => version_compare($first, $second))
			->values()
			->all();
	}

	private function binaryOf(string $version): string
	{
		$folder = $this->herdBin() . '/php' . str_replace('.', '', $version);

		return is_file($folder . '/php.exe') ? $folder . '/php.exe' : $folder . '/php';
	}

	/**
	 * Whether a PHP version, taken as major.minor with any patch, meets a
	 * Composer requirement. Covers the forms projects use: ^, ~, wildcards,
	 * comparisons, and alternatives joined by ||.
	 */
	private function meets(string $version, string $requirement): bool
	{
		foreach (preg_split('/\s*\|\|?\s*/', $requirement) ?: [] as $alternative) {
			// >= 7 is the same bound as >=7
			$terms = array_filter(preg_split('/[\s,]+/', (string) preg_replace('/(>=|<=|!=|==|>|<|=)\s+/', '$1', $this->withoutHyphenRange(trim($alternative)))) ?: []);

			// the lowest and the highest patch both stand for the version, so a bound inside the minor still lets it through
			foreach ([$version . '.0', $version . '.99'] as $release) {
				if ($terms !== [] && collect($terms)->every(fn (string $term): bool => $this->meetsTerm($release, $term))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * A range such as 8.1 - 8.6 written as two bounds: from the first, up to
	 * and including all of the second.
	 */
	private function withoutHyphenRange(string $alternative): string
	{
		if (preg_match('/^(\d+(?:\.\d+){0,2})\s+-\s+(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $alternative, $parts) !== 1) {
			return $alternative;
		}

		[, $lower, $major, $minor, $patch] = $parts + [3 => '', 4 => ''];

		$upper = match (true) {
			$patch !== '' => '<=' . $major . '.' . $minor . '.' . $patch,
			$minor !== '' => '<' . $major . '.' . ($minor + 1),
			default => '<' . ($major + 1),
		};

		return '>=' . $lower . ' ' . $upper;
	}

	private function meetsTerm(string $release, string $term): bool
	{
		if ($term === '*') {
			return true;
		}

		if (preg_match('/^(\^|~)(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $term, $parts) === 1) {
			[, $operator, $major, $minor, $patch] = $parts + [4 => '', 3 => ''];
			$lower = $major . '.' . ($minor === '' ? '0' : $minor) . '.' . ($patch === '' ? '0' : $patch);
			$upper = $operator === '^' || $minor === '' || ($operator === '~' && $patch === '')
				? ($major + 1) . '.0.0'
				: $major . '.' . ($minor + 1) . '.0';

			return version_compare($release, $lower, '>=') && version_compare($release, $upper, '<');
		}

		if (preg_match('/^(\d+)\.\*$/', $term, $parts) === 1) {
			return str_starts_with($release, $parts[1] . '.');
		}

		if (preg_match('/^(\d+)\.(\d+)\.\*$/', $term, $parts) === 1) {
			return version_compare($release, $parts[1] . '.' . $parts[2] . '.0', '>=') && version_compare($release, $parts[1] . '.' . ($parts[2] + 1) . '.0', '<');
		}

		if (preg_match('/^(>=|<=|>|<|!=|==|=)?(\d+(?:\.\d+){0,2})$/', $term, $parts) === 1) {
			$operator = $parts[1] === '' || $parts[1] === '=' ? '==' : $parts[1];

			// a bare 8.4 means any 8.4
			if ($operator === '==' && substr_count($parts[2], '.') < 2) {
				return str_starts_with($release . '.', $parts[2] . '.');
			}

			return version_compare($release, $parts[2], $operator);
		}

		// a form this does not read, such as a -dev suffix, does not rule a version out
		return true;
	}

	private function herdDefaultVersion(): ?string
	{
		$herdBin = $this->herdBin();
		$configuration = $herdBin === null ? null : json_decode((string) @file_get_contents(dirname($herdBin) . '/config/valet/config.json'), true);
		$version = is_array($configuration) ? ($configuration['php'] ?? null) : null;

		return is_string($version) && preg_match('/^(\d+\.\d+)/', $version, $parts) === 1 ? $parts[1] : null;
	}

	private function herdBin(): ?string
	{
		$configured = (string) config('devdock.herd.bin');

		if ($configured !== '') {
			return rtrim(strtr($configured, '\\', '/'), '/');
		}

		$home = (string) (getenv('USERPROFILE') ?: getenv('HOME'));
		$folder = rtrim(strtr($home, '\\', '/'), '/') . '/.config/herd/bin';

		return $home !== '' && is_dir($folder) ? $folder : null;
	}
}
