<?php

declare(strict_types=1);

namespace Src\Account\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Account\Contracts\AccountRepositoryContract;
use Src\Account\Repositories\AccountRepository;

class AccountServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(AccountRepositoryContract::class, AccountRepository::class);
	}
}
