<?php

declare(strict_types=1);

namespace Src\Prompt\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * How a project sets up Tailwind: the theme from its config and the official
 * plugins it installs, so a sketch can be drawn with the same classes.
 */
final class TailwindSetupObject extends ValueObject
{
	/**
	 * @param  array<string, mixed>  $theme
	 * @param  list<string>  $plugins  short names such as forms or typography
	 */
	public function __construct(
		public readonly array $theme,
		public readonly array $plugins,
	) {
		parent::__construct();
	}
}
