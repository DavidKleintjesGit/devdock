<?php

declare(strict_types=1);

namespace Src\Library\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Library\Contracts\LibraryDiskRepositoryContract;
use Src\Library\Contracts\LibraryRemoteRepositoryContract;
use Src\Library\Contracts\LibraryRepositoryContract;
use Src\Library\Repositories\LibraryDiskRepository;
use Src\Library\Repositories\LibraryRemoteRepository;
use Src\Library\Repositories\LibraryRepository;

class LibraryServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(LibraryRepositoryContract::class, LibraryRepository::class);
		$this->app->singleton(LibraryDiskRepositoryContract::class, LibraryDiskRepository::class);
		$this->app->singleton(LibraryRemoteRepositoryContract::class, LibraryRemoteRepository::class);
	}
}
