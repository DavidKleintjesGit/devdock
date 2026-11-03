<?php

declare(strict_types=1);

namespace Src\Access\Enums;

/**
 * Who DevDock starts Claude Code as. Each role reaches its own set of tools,
 * shown on the Access page and handed to Claude Code when it starts.
 */
enum AgentRoleEnum: string
{
	case AGENT = 'agent';
	case LENS = 'lens';
	case HELPER = 'helper';

	public function label(): string
	{
		return __(match ($this) {
			self::AGENT => 'Agent',
			self::LENS => 'Lens',
			self::HELPER => 'Hulpvraag',
		});
	}

	public function explanation(): string
	{
		return __(match ($this) {
			self::AGENT => 'Werkt de taak uit in de werkkopie, ook als je hem zelf in een terminal opent.',
			self::LENS => 'Beoordeelt een ronde en levert alleen een oordeel.',
			self::HELPER => 'Beantwoordt één korte vraag, zoals een commitbericht of een branchnaam.',
		});
	}

	/**
	 * Whether this role may change files in the working copy.
	 */
	public function editsFiles(): bool
	{
		return $this === self::AGENT;
	}
}
