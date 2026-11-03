<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Objects\LensBriefObject;

/**
 * The longer assignment of a lens, from resources/lenses/<name>.md, without
 * its front matter; null for a lens that has none.
 *
 * @method static ?LensBriefObject execute(string $lensName)
 */
class GetLensBriefAction extends Command
{
	public function __construct(
		public readonly string $lensName,
	) {}

	public function handle(): ?LensBriefObject
	{
		$name = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($this->lensName)), '-');
		$file = resource_path('lenses/' . $name . '.md');

		if ($name === '' || ! is_file($file)) {
			return null;
		}

		$contents = str_replace("\r\n", "\n", (string) file_get_contents($file));

		if (preg_match('/\A---\n.*?\n---\n/s', $contents, $frontMatter) === 1) {
			$contents = substr($contents, strlen($frontMatter[0]));
		}

		return new LensBriefObject(text: trim($contents));
	}
}
