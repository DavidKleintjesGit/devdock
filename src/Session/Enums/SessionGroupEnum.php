<?php

declare(strict_types=1);

namespace Src\Session\Enums;

enum SessionGroupEnum: string
{
	case TODAY = 'Vandaag';
	case THIS_WEEK = 'Deze week';
	case OLDER = 'Ouder';

	public function label(): string
	{
		return __($this->value);
	}
}
