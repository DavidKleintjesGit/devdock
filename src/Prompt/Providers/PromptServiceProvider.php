<?php

declare(strict_types=1);

namespace Src\Prompt\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Repositories\ClaudeProcessRepository;
use Src\Prompt\Repositories\PromptRunRepository;

class PromptServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(PromptRunRepositoryContract::class, PromptRunRepository::class);
		$this->app->singleton(ClaudeProcessRepositoryContract::class, ClaudeProcessRepository::class);
	}
}
