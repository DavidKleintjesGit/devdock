<?php

declare(strict_types=1);

namespace Src\Rule\Actions;

use Src\Packages\Cqrs\Command;
use Src\Rule\Contracts\PermissionRepositoryContract;
use Src\Rule\Objects\PermissionRuleObjectCollection;

/**
 * Alle toestemmingen op één hoop, zodat je op één plek ziet wat een agent mag
 * en wat niet, en waar dat vandaan komt.
 *
 * @method static PermissionRuleObjectCollection execute()
 */
class GetPermissionsAction extends Command
{
	public function handle(PermissionRepositoryContract $permissionRepository): PermissionRuleObjectCollection
	{
		return $permissionRepository->getAll();
	}
}
