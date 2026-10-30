<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The name a working copy answers on in the browser. Branch first, so every
 * copy of one project stands together in the list, and nothing of one project
 * can ever take the name of another.
 */
final class SiteName extends ValueObject
{
	public function __construct(
		public readonly string $name,
	) {
		parent::__construct();
	}

	public static function for(string $project, string $branch): self
	{
		return new self(self::readable($branch) . '.' . self::readable($project));
	}

	/**
	 * A hostname holds letters, digits, dashes and the dots between its parts.
	 * Everything a branch name may carry on top of that becomes a dash.
	 */
	private static function readable(string $part): string
	{
		$readable = preg_replace('/[^a-z0-9.]+/', '-', strtolower($part)) ?? '';

		return trim($readable, '-.');
	}
}
