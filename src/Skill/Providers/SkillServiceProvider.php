<?php

declare(strict_types=1);

namespace Src\Skill\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Skill\Contracts\SkillFileSystemRepositoryContract;
use Src\Skill\Contracts\SkillRepositoryContract;
use Src\Skill\Repositories\SkillFileSystemRepository;
use Src\Skill\Repositories\SkillRepository;

class SkillServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SkillRepositoryContract::class, SkillRepository::class);
		$this->app->singleton(SkillFileSystemRepositoryContract::class, SkillFileSystemRepository::class);
	}
}
