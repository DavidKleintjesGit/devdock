<?php

declare(strict_types=1);

namespace Src\Fetch\Enums;

enum FetchStatusEnum: string
{
	case RUNNING = 'running';
	case DONE = 'done';
	case FAILED = 'failed';

	public function label(): string
	{
		return __(match ($this) {
			self::RUNNING => 'Bezig',
			self::DONE => 'Klaar',
			self::FAILED => 'Mislukt',
		});
	}
}
