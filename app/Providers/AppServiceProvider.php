<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	public function register(): void
	{
	}

	public function boot(): void
	{
		Model::shouldBeStrict($this->app->isLocal());

		// times are kept in UTC, as Jira, Bitbucket and git hand them over; a page shows them in the zone you live in
		$toDisplayZone = fn (): CarbonInterface => $this->copy()->setTimezone((string) config('devdock.timezone'));
		Carbon::macro('toDisplayZone', $toDisplayZone);
		CarbonImmutable::macro('toDisplayZone', $toDisplayZone);
	}
}
