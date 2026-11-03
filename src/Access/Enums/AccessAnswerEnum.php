<?php

declare(strict_types=1);

namespace Src\Access\Enums;

/**
 * Whether a role may do something: outright, only behind a guard that checks
 * each call, or not at all.
 */
enum AccessAnswerEnum: string
{
	case YES = 'yes';
	case GUARDED = 'guarded';
	case NO = 'no';

	public function label(): string
	{
		return __(match ($this) {
			self::YES => 'ja',
			self::GUARDED => 'ja, achter de bewaking',
			self::NO => 'nee',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::YES => 'green',
			self::GUARDED => 'amber',
			self::NO => 'red',
		};
	}
}
