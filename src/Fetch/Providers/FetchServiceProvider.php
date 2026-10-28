<?php

declare(strict_types=1);

namespace Src\Fetch\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Fetch\Contracts\FetchProcessRepositoryContract;
use Src\Fetch\Contracts\FetchRunRepositoryContract;
use Src\Fetch\Repositories\FetchProcessRepository;
use Src\Fetch\Repositories\FetchRunRepository;

class FetchServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(FetchRunRepositoryContract::class, FetchRunRepository::class);
		$this->app->singleton(FetchProcessRepositoryContract::class, FetchProcessRepository::class);
	}
}
