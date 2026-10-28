<?php

declare(strict_types=1);

namespace Src\Setting\Contracts;

use Src\Setting\Objects\SettingEntity;
use Src\Setting\Objects\SettingObject;

interface SettingRepositoryContract
{
	public function get(): SettingEntity;

	/**
	 * Writes the settings away and makes sure both the pages and the fetch
	 * command read them at once.
	 */
	public function update(SettingObject $settingObject): SettingEntity;
}
