<?php

declare(strict_types=1);

namespace Src\Setting\Actions;

use Src\Packages\Cqrs\Command;
use Src\Setting\Contracts\SettingRepositoryContract;
use Src\Setting\Objects\SettingEntity;

/**
 * @method static SettingEntity execute()
 */
class GetSettingsAction extends Command
{
	public function handle(SettingRepositoryContract $settingRepository): SettingEntity
	{
		return $settingRepository->get();
	}
}
