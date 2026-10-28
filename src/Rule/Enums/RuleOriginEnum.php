<?php

declare(strict_types=1);

namespace Src\Rule\Enums;

enum RuleOriginEnum: string
{
	case FROM_CORE = 'core';
	case BEHIND_CORE = 'behind';
	case ONLY_HERE = 'here';
	case ONLY_YOURS = 'mine';

	public function label(): string
	{
		return __(match ($this) {
			self::FROM_CORE => 'Uit core',
			self::BEHIND_CORE => 'Achter op core',
			self::ONLY_HERE => 'Alleen hier',
			self::ONLY_YOURS => 'Alleen van jou',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::FROM_CORE => 'gray',
			self::BEHIND_CORE => 'red',
			self::ONLY_HERE => 'blue',
			self::ONLY_YOURS => 'green',
		};
	}
}
