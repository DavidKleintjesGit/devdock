<?php

declare(strict_types=1);

namespace Src\Setting\Actions;

use Src\Packages\Cqrs\Command;
use Src\Setting\Contracts\SettingRepositoryContract;
use Src\Setting\Objects\SettingEntity;
use Src\Setting\Objects\SettingObject;

/**
 * @method static SettingEntity execute(SettingObject $settingObject)
 */
class SaveSettingsAction extends Command
{
	public function __construct(
		public readonly SettingObject $settingObject,
	) {
	}

	public function handle(SettingRepositoryContract $settingRepository): SettingEntity
	{
		return $settingRepository->update($this->settingObject);
	}
}
