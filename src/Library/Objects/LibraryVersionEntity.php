<?php

declare(strict_types=1);

namespace Src\Library\Objects;

use Carbon\CarbonImmutable;
use Src\Library\Enums\VersionSourceEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * One version of a library rule or skill, with its whole content.
 */
class LibraryVersionEntity extends ValueObject
{
	public function __construct(
		public readonly int $id,
		public readonly int $number,
		public readonly string $contents,
		public readonly string $hash,
		public readonly VersionSourceEnum $source,
		public readonly ?string $note,
		public readonly ?string $project,
		public readonly ?CarbonImmutable $createdAt,
	) {
		parent::__construct();
	}

	/**
	 * Line endings and trailing space do not make a version.
	 */
	public static function hashOf(string $contents): string
	{
		return sha1(trim(str_replace("\r\n", "\n", $contents)));
	}

	public function label(): string
	{
		return 'v' . $this->number;
	}

	public function lines(): int
	{
		return self::lineCountOf($this->contents);
	}

	/**
	 * The lines of a rule, or of the SKILL.md of a skill, whose version holds all its files packed as JSON.
	 */
	public static function lineCountOf(string $contents): int
	{
		$files = json_decode($contents, true);
		$text = is_array($files) && is_string($files['SKILL.md'] ?? null) ? $files['SKILL.md'] : $contents;

		return substr_count($text, "\n") + 1;
	}
}
