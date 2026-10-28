<?php

declare(strict_types=1);

namespace Src\Ticket\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Ticket\Contracts\TicketRepositoryContract;
use Src\Ticket\Contracts\TicketSourceRepositoryContract;
use Src\Ticket\Repositories\JiraTicketRepository;
use Src\Ticket\Repositories\TicketRepository;

class TicketServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TicketRepositoryContract::class, TicketRepository::class);
		$this->app->singleton(TicketSourceRepositoryContract::class, JiraTicketRepository::class);
	}
}
