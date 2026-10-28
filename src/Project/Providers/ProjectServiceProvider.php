<?php

declare(strict_types=1);

namespace Src\Project\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Project\Contracts\ProjectRepositoryContract;
use Src\Project\Contracts\ProjectScanRepositoryContract;
use Src\Project\Repositories\ProjectRepository;
use Src\Project\Repositories\ProjectScanRepository;

class ProjectServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(ProjectRepositoryContract::class, ProjectRepository::class);
		$this->app->singleton(ProjectScanRepositoryContract::class, ProjectScanRepository::class);
	}
}
