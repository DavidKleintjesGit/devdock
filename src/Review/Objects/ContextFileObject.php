<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One piece of what an agent or a lens is given: a file, or a text DevDock
 * puts together, with where it comes from.
 */
class ContextFileObject extends ValueObject
{
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly ?string $group = null,
		public readonly ?string $description = null,
		public readonly ?string $path = null,
		public readonly ?string $contents = null,
		// the card tab where this is read in full, when it is not a text of its own
		public readonly ?string $tab = null,
	) {
		parent::__construct();
	}

	/**
	 * The route of the page where this piece is set, or null when no page sets it.
	 */
	public function settingsRoute(): ?string
	{
		return match (true) {
			str_starts_with($this->key, 'global:'), str_starts_with($this->key, 'checkout:'), str_starts_with($this->key, 'project:') => 'rules',
			str_starts_with($this->key, 'skill:') => 'skills',
			str_starts_with($this->key, 'step:'), in_array($this->key, ['standing', 'chat', 'project', 'definition', 'assignment', 'instruction'], true) => 'workflows',
			default => null,
		};
	}

	/**
	 * The button text that leads to the page where this piece is set.
	 */
	public function settingsLabel(): ?string
	{
		return match ($this->settingsRoute()) {
			'rules' => __('Instellen in Regels'),
			'skills' => __('Instellen in Skills'),
			'workflows' => __('Instellen in Workflows'),
			default => null,
		};
	}
}
