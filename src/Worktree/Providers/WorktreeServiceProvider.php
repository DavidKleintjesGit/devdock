<?php

declare(strict_types=1);

namespace Src\Worktree\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorkingCopyStateRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Repositories\BranchStandingRepository;
use Src\Worktree\Repositories\WorkingCopyRepository;
use Src\Worktree\Repositories\WorkingCopyStateRepository;
use Src\Worktree\Repositories\WorktreeDatabaseRepository;
use Src\Worktree\Repositories\WorktreeRepository;

class WorktreeServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(WorktreeRepositoryContract::class, WorktreeRepository::class);
		$this->app->singleton(WorkingCopyRepositoryContract::class, WorkingCopyRepository::class);
		$this->app->singleton(WorktreeDatabaseRepositoryContract::class, WorktreeDatabaseRepository::class);
		$this->app->singleton(BranchStandingRepositoryContract::class, BranchStandingRepository::class);
		$this->app->singleton(WorkingCopyStateRepositoryContract::class, WorkingCopyStateRepository::class);
	}
}
