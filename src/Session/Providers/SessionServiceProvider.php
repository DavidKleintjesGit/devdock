<?php

declare(strict_types=1);

namespace Src\Session\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Session\Contracts\SessionRepositoryContract;
use Src\Session\Repositories\SessionFileRepository;
use Src\Session\Repositories\SessionRepository;

class SessionServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SessionRepositoryContract::class, SessionRepository::class);
		$this->app->singleton(SessionFileRepositoryContract::class, SessionFileRepository::class);
	}
}
