<?php

declare(strict_types=1);

namespace Src\Session\Enums;

enum SessionStatusEnum: string
{
	case ACTIVE = 'actief';
	case QUIET = 'stil';
	case ABANDONED = 'afgebroken';

	public function label(): string
	{
		return __(match ($this) {
			self::ACTIVE => 'Actief',
			self::QUIET => 'Stil',
			self::ABANDONED => 'Afgebroken',
		});
	}

	public function tone(): string
	{
		return match ($this) {
			self::ACTIVE => 'green',
			self::QUIET => 'gray',
			self::ABANDONED => 'amber',
		};
	}
}
