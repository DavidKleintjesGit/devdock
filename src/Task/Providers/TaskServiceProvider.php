<?php

declare(strict_types=1);

namespace Src\Task\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Task\Contracts\TaskRepositoryContract;
use Src\Task\Repositories\TaskRepository;

class TaskServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TaskRepositoryContract::class, TaskRepository::class);
	}
}
