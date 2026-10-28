<?php

declare(strict_types=1);

namespace Src\Rule\Enums;

enum RuleGroupEnum: string
{
	case PROJECT_INSTRUCTIONS = 'Projectinstructies';
	case SHARED_RULES = 'Gedeelde regels';
	case OWN_RULES = 'Eigen regels';
	case DESIGN = 'Ontwerp';

	public function label(): string
	{
		return __($this->value);
	}
}
