<?php

declare(strict_types=1);

namespace Src\Review\Enums;

/**
 * How a review round stands. A round runs on one delivery and holds for the
 * commit it ran on.
 */
enum RoundStatusEnum: string
{
	case RUNNING = 'running';
	case PASSED = 'passed';
	case FAILED = 'failed';
	case STOPPED = 'stopped';

	public function label(): string
	{
		return __(match ($this) {
			self::RUNNING => 'Ronde bezig',
			self::PASSED => 'Alle lenzen PASS',
			self::FAILED => 'Punten open',
			self::STOPPED => 'Afgebroken',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::RUNNING => 'amber',
			self::PASSED => 'green',
			self::FAILED => 'red',
			self::STOPPED => 'gray',
		};
	}
}
