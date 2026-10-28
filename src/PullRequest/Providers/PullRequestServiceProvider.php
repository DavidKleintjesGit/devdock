<?php

declare(strict_types=1);

namespace Src\PullRequest\Providers;

use Illuminate\Support\ServiceProvider;
use Src\PullRequest\Contracts\PullRequestRepositoryContract;
use Src\PullRequest\Contracts\PullRequestSourceRepositoryContract;
use Src\PullRequest\Repositories\BitbucketPullRequestRepository;
use Src\PullRequest\Repositories\PullRequestRepository;

class PullRequestServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(PullRequestRepositoryContract::class, PullRequestRepository::class);
		$this->app->singleton(PullRequestSourceRepositoryContract::class, BitbucketPullRequestRepository::class);
	}
}
