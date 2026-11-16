<?php

declare(strict_types=1);

namespace Src\Review\Enums;

use Src\Review\Objects\ContextFileObject;

/**
 * Where a piece of context comes from, in the order it steers the answer: what
 * DevDock asks, the rules of the project, its skills, your own folder, and the
 * conversation so far.
 */
enum ContextOriginEnum: string
{
	case DEVDOCK = 'devdock';
	case PROJECT_RULES = 'rules';
	case PROJECT_SKILLS = 'skills';
	case HOME = 'home';
	case CONVERSATION = 'conversation';

	public static function of(ContextFileObject $contextFileObject, string $home): self
	{
		$path = strtolower(str_replace('\\', '/', (string) $contextFileObject->path));

		return match (true) {
			$contextFileObject->key === 'conversation' => self::CONVERSATION,
			str_starts_with($contextFileObject->key, 'global:'), $contextFileObject->key === 'memory' => self::HOME,
			str_starts_with($contextFileObject->key, 'skill:') => str_starts_with($path, strtolower($home)) || str_starts_with($path, '~/.claude') ? self::HOME : self::PROJECT_SKILLS,
			str_starts_with($contextFileObject->key, 'checkout:'), str_starts_with($contextFileObject->key, 'project:') => self::PROJECT_RULES,
			default => self::DEVDOCK,
		};
	}

	public function label(): string
	{
		return __(match ($this) {
			self::DEVDOCK => 'Opdracht van DevDock',
			self::PROJECT_RULES => 'Projectregels',
			self::PROJECT_SKILLS => 'Skills van het project',
			self::HOME => 'Jouw map',
			self::CONVERSATION => 'Gesprek',
		});
	}

	public function hint(): string
	{
		return __(match ($this) {
			self::DEVDOCK => 'opdracht, werkwijze en wat DevDock er per taak bij zet',
			self::PROJECT_RULES => 'CLAUDE.md, CLAUDE.local.md en .claude/rules',
			self::PROJECT_SKILLS => '.claude/skills van dit project',
			self::HOME => '~/.claude: regels, skills, plugins en geheugen',
			self::CONVERSATION => 'eerdere beurten, hervat met de sessiesleutel',
		});
	}

	/**
	 * The colour of this origin in the bar that shows how the context is made up.
	 */
	public function tone(): string
	{
		return match ($this) {
			self::DEVDOCK => 'teal',
			self::PROJECT_RULES => 'blue',
			self::PROJECT_SKILLS => 'mint',
			self::HOME => 'amber',
			self::CONVERSATION => 'gray',
		};
	}
}
