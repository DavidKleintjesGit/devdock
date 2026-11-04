<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * Where a finding of a lens stands: still to be fixed, fixed as a lens saw
 * in a later round, or set aside by you with a reason.
 */
enum FindingStatusEnum: string
{
	case OPEN = 'open';
	case RESOLVED = 'resolved';
	case IGNORED = 'ignored';

	public function label(): string
	{
		return __(match ($this) {
			self::OPEN => 'open',
			self::RESOLVED => 'opgelost',
			self::IGNORED => 'genegeerd',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::OPEN => 'red',
			self::RESOLVED => 'green',
			self::IGNORED => 'muted',
		};
	}
}
