<?php

declare(strict_types=1);

namespace Src\Review\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\LensProcessRepositoryContract;
use Src\Review\Contracts\LensRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Review\Repositories\AgentContextRepository;
use Src\Review\Repositories\LensProcessRepository;
use Src\Review\Repositories\LensRepository;
use Src\Review\Repositories\ReviewRoundRepository;

class ReviewServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(AgentContextRepositoryContract::class, AgentContextRepository::class);
		$this->app->singleton(LensRepositoryContract::class, LensRepository::class);
		$this->app->singleton(LensProcessRepositoryContract::class, LensProcessRepository::class);
		$this->app->singleton(ReviewRoundRepositoryContract::class, ReviewRoundRepository::class);
	}
}
