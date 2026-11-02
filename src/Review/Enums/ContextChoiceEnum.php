<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Whose context is on screen: what only the agent gets, what only one lens
 * gets, or what both get alike.
 */
enum ContextChoiceEnum: string
{
	case AGENT = 'agent';
	case LENS = 'lens';
	case RULES = 'rules';
	case SKILLS = 'skills';

	/**
	 * The line under the picker that says what this choice holds.
	 */
	public function explanation(): string
	{
		return __(match ($this) {
			self::AGENT => 'Wat alleen de agent krijgt. Rules en skills krijgt hij ook; die staan onder Rules en Skills.',
			self::LENS => 'Wat alleen deze lens krijgt, in de laatste ronde. Geen gesprek en geen taakbestand van de agent.',
			self::RULES => 'Gelden voor de agent én elke lens in deze werkkopie.',
			self::SKILLS => 'Gelden voor de agent én elke lens. Claude krijgt alleen de omschrijving mee, en leest de rest pas als de skill gebruikt wordt.',
		});
	}

	/**
	 * The same line in Workflows, before any task has added its own.
	 */
	public function explanationOutsideTask(): string
	{
		return __(match ($this) {
			self::AGENT => 'Wat alleen de agent krijgt. Per taak komen daar waar hij staat, het gesprek en de opdracht van de beurt bij.',
			self::LENS => 'Wat deze lens krijgt. Per taak vult DevDock de bestanden en het vorige rapport in.',
			self::RULES => 'Gelden voor de agent én elke lens. Een werkkopie leest ze uit zijn eigen map.',
			self::SKILLS => 'Gelden voor de agent én elke lens. Claude krijgt alleen de omschrijving mee, en leest de rest pas als de skill gebruikt wordt.',
		});
	}
}
