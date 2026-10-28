<?php

declare(strict_types=1);

namespace Src\Setting\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Setting\Contracts\SettingRepositoryContract;
use Src\Setting\Repositories\EnvironmentSettingRepository;

class SettingServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SettingRepositoryContract::class, EnvironmentSettingRepository::class);
	}
}
