<?php

declare(strict_types=1);

namespace Src\Rule\Contracts;

use Src\Rule\Objects\PermissionRuleObjectCollection;

interface PermissionRepositoryContract
{
	/**
	 * Elke toestemmingsregel die op deze machine staat, van jou en van de
	 * projecten, met erbij of hij meetelt.
	 */
	public function getAll(): PermissionRuleObjectCollection;
}
