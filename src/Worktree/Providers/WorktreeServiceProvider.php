<?php

declare(strict_types=1);

namespace Src\Worktree\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Repositories\WorkingCopyRepository;
use Src\Worktree\Repositories\WorktreeRepository;

class WorktreeServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(WorktreeRepositoryContract::class, WorktreeRepository::class);
		$this->app->singleton(WorkingCopyRepositoryContract::class, WorkingCopyRepository::class);
	}
}
