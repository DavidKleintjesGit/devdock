<?php

declare(strict_types=1);

namespace Src\Board\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Board\Contracts\TodoRepositoryContract;
use Src\Board\Repositories\TodoRepository;

class BoardServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TodoRepositoryContract::class, TodoRepository::class);
	}
}
