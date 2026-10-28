<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Src\Fetch\Actions\CountConnectedSourcesAction;
use Src\Fetch\Actions\GetLatestFetchRunAction;
use Src\Fetch\Actions\StartFetchAction;

class FetchStatus extends Component
{
	public function fetchNow(): void
	{
		StartFetchAction::execute();
	}

	public function render(): View
	{
		$fetchRunEntity = GetLatestFetchRunAction::execute();

		return view('livewire.fetch-status', [
			'run' => $fetchRunEntity,
			'running' => $fetchRunEntity?->isRunning() ?? false,
			'connected' => CountConnectedSourcesAction::execute(),
			'updated' => $fetchRunEntity?->finishedAt?->format('H:i'),
		]);
	}
}
