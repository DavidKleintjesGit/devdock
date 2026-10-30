<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * Eén toestemmingsregel zoals Claude Code hem leest: waar hij vandaan komt,
 * of hij toestaat, weigert of vraagt, en op welk commando hij slaat.
 *
 * Een regel die niet meetelt is er ook een: een project dat niet vertrouwd is
 * levert zijn regels wel aan, maar Claude Code negeert ze. Dat hoort zichtbaar
 * te zijn, want anders is het een verrassing op het verkeerde moment.
 */
class PermissionRuleObject extends ValueObject
{
	public function __construct(
		public readonly string $kind,
		public readonly string $pattern,
		public readonly string $source,
		public readonly ?string $project,
		public readonly bool $counts,
	) {
		parent::__construct();
	}

	/**
	 * De opvallendheid van deze regel: toestaan is de normale gang van zaken en
	 * blijft grijs, weigeren en vragen springen eruit, en wat genegeerd wordt
	 * vervaagt.
	 */
	public function tone(): string
	{
		if (! $this->counts) {
			return 'skip';
		}

		return match ($this->kind) {
			'deny' => 'deny',
			'ask' => 'ask',
			default => 'allow',
		};
	}

	public function label(): string
	{
		return __(match ($this->kind) {
			'deny' => 'Nooit',
			'ask' => 'Vraagt eerst',
			default => 'Mag',
		});
	}
}
