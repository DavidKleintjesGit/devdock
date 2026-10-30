<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One reviewer our own agent calls on. A lens knows which paths it watches
 * and what it is looking for; it joins a round as soon as one changed file
 * falls inside its paths.
 */
class LensEntity extends ValueObject
{
	/**
	 * @param  array<int, string>  $paths  glob patterns, relative to the project
	 */
	public function __construct(
		public readonly LensId $lensId,
		public readonly string $name,
		public readonly string $description,
		public readonly array $paths,
		public readonly ?string $command,
		public readonly bool $isEnabled,
	) {
		parent::__construct();
	}

	/**
	 * Whether this lens has anything to say about a round that touched these
	 * files. A lens with a command of its own always runs: its judgement does
	 * not hang on which files moved.
	 */
	public function matches(array $files): bool
	{
		if ($this->command !== null) {
			return true;
		}

		foreach ($files as $file) {
			foreach ($this->paths as $pattern) {
				if (fnmatch($pattern, $file, FNM_NOESCAPE)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * The files of a round this lens looks at.
	 *
	 * @param  array<int, string>  $files
	 * @return array<int, string>
	 */
	public function touches(array $files): array
	{
		if ($this->command !== null) {
			return $files;
		}

		return array_values(array_filter(
			$files,
			fn (string $file) => $this->matches([$file]),
		));
	}
}
