<?php

declare(strict_types=1);

namespace Src\Rule\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Rule\Contracts\PermissionRepositoryContract;
use Src\Rule\Contracts\RuleFileRepositoryContract;
use Src\Rule\Contracts\RuleFileSystemRepositoryContract;
use Src\Rule\Repositories\PermissionRepository;
use Src\Rule\Repositories\RuleFileRepository;
use Src\Rule\Repositories\RuleFileSystemRepository;

class RuleServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(RuleFileRepositoryContract::class, RuleFileRepository::class);
		$this->app->singleton(PermissionRepositoryContract::class, PermissionRepository::class);
		$this->app->singleton(RuleFileSystemRepositoryContract::class, RuleFileSystemRepository::class);
	}
}
