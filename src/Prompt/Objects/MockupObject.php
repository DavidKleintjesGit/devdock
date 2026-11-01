<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * A screen the agent drew in an answer: its markup, and the stylesheets of
 * the project it is drawn with, as paths under the public folder.
 */
class MockupObject extends ValueObject
{
	/**
	 * @param  array<int, string>  $stylesheets
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $stylesheets,
	) {
		parent::__construct();
	}
}
