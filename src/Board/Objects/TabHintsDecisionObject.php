<?php

declare(strict_types=1);

namespace Src\Board\Objects;

use Src\Board\Enums\CardSubtabEnum;
use Src\Board\Enums\CardTabEnum;
use Src\Board\Enums\HintToneEnum;
use Src\Packages\ValueObject\ValueObject;

/**
 * The colours of the hint dots of one card, per tab and per subtab: red before
 * amber, each colour once.
 */
final class TabHintsDecisionObject extends ValueObject
{
	/**
	 * @param  array<string, list<HintToneEnum>>  $tabTones
	 * @param  array<string, list<HintToneEnum>>  $subtabTones
	 */
	public function __construct(
		public readonly array $tabTones,
		public readonly array $subtabTones,
	) {
		parent::__construct();
	}

	/**
	 * @return list<HintToneEnum>
	 */
	public function forTab(CardTabEnum $cardTab): array
	{
		return $this->tabTones[$cardTab->value] ?? [];
	}

	/**
	 * @return list<HintToneEnum>
	 */
	public function forSubtab(CardSubtabEnum $cardSubtab): array
	{
		return $this->subtabTones[$cardSubtab->value] ?? [];
	}
}
