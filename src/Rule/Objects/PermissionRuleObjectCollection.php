<?php

declare(strict_types=1);

namespace Src\Rule\Objects;

use Src\Packages\ValueObject\ValueObjectCollection;

class PermissionRuleObjectCollection extends ValueObjectCollection
{
	protected function __construct(PermissionRuleObject ...$permissionRuleObjects) {}
}
