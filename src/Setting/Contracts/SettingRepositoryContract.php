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

	/**
	 * Writes the threshold of the standard workflow: how many rounds, and
	 * what happens after.
	 */
	public function saveThreshold(int $maxRounds, string $afterRounds): void;

	/**
	 * Sets who decides on the findings of a round, for every project without a choice of its own.
	 */
	public function saveReviewMode(string $reviewMode): void;
}
