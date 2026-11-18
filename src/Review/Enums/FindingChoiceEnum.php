<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * What you decide for a finding after a round: the agent fixes it, it stays
 * as it is with your reason, or it is already done.
 */
enum FindingChoiceEnum: string
{
	case FIX = 'fix';
	case IGNORE = 'ignore';
	case RESOLVED = 'resolved';

	public function label(): string
	{
		return __(match ($this) {
			self::FIX => 'Fixen',
			self::IGNORE => 'Negeren',
			self::RESOLVED => 'Opgelost',
		});
	}

	/**
	 * The status the finding takes once you send your choice; a fix stays open until a lens sees it done.
	 */
	public function findingStatus(): ?FindingStatusEnum
	{
		return match ($this) {
			self::FIX => null,
			self::IGNORE => FindingStatusEnum::IGNORED,
			self::RESOLVED => FindingStatusEnum::RESOLVED,
		};
	}
}
