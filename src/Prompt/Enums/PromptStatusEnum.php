<?php

declare(strict_types=1);

namespace Src\Prompt\Enums;

enum PromptStatusEnum: string
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

	public function tone(): string
	{
		return match ($this) {
			self::RUNNING => 'blue',
			self::DONE => 'green',
			self::FAILED => 'red',
		};
	}
}
