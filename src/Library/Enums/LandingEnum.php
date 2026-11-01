<?php

declare(strict_types=1);

namespace Src\Library\Enums;

/**
 * Where a library rule is put down in a project, so the agent that works
 * there reads it.
 */
enum LandingEnum: string
{
	case CLAUDE = 'claude';
	case CURSOR = 'cursor';
	case AGENTS = 'agents';
	case CUSTOM = 'custom';

	public function label(): string
	{
		return __(match ($this) {
			self::CLAUDE => '.claude/rules',
			self::CURSOR => '.cursor/rules',
			self::AGENTS => 'Als blok in AGENTS.md',
			self::CUSTOM => 'Een andere map',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::CLAUDE => 'Eén bestand per regel. Claude Code leest het; andere agents niet.',
			self::CURSOR => 'Eén .mdc-bestand per regel, met de kop die Cursor verwacht.',
			self::AGENTS => 'Elke regel wordt een eigen blok tussen twee markeringen, zodat bijwerken alleen dat blok vervangt.',
			self::CUSTOM => 'Eén bestand per regel, in een map die je zelf kiest.',
		});
	}

	/**
	 * Whether DevDock can tell from the project that this place is in use.
	 */
	public function isFound(): bool
	{
		return $this !== self::CUSTOM;
	}

	/**
	 * The file a rule of this name lands in, relative to the project.
	 */
	public function pathFor(string $name, ?string $folder): string
	{
		return match ($this) {
			self::CLAUDE => '.claude/rules/' . $name,
			self::CURSOR => '.cursor/rules/' . pathinfo($name, PATHINFO_FILENAME) . '.mdc',
			self::AGENTS => 'AGENTS.md',
			self::CUSTOM => trim((string) $folder, '/') . '/' . $name,
		};
	}
}
