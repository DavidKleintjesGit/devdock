<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One card in the confirmation of a clean-up: what goes, and the work that
 * would be lost with it.
 */
class CardCleanUpObject extends ValueObject
{
	/**
	 * @param  list<string>  $goes
	 */
	public function __construct(
		public readonly string $card,
		public readonly string $label,
		public readonly string $title,
		public readonly array $goes,
		public readonly string $workAtStake,
		public readonly bool $isTodo,
	) {
		parent::__construct();
	}

	public function losesWork(): bool
	{
		return $this->workAtStake !== '';
	}
}
