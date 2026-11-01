<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Src\Fetch\Actions\CountConnectedSourcesAction;
use Src\Fetch\Actions\GetLatestFetchRunAction;
use Src\Fetch\Actions\RefreshWhenStaleAction;
use Src\Fetch\Actions\StartFetchAction;
use Src\Fetch\Objects\FetchRunEntity;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;

class FetchStatus extends Component
{
	/**
	 * How often an open page asks whether the sources are due; asking is a
	 * single query, the round itself only starts when it is time.
	 */
	private const LOOK_EVERY_SECONDS = 15;

	/**
	 * Opening the board is itself a moment to check whether the sources have
	 * gone stale.
	 */
	public function mount(): void
	{
		RefreshWhenStaleAction::execute();
	}

	public function fetchNow(): void
	{
		// je drukt hier juist omdat je denkt dat het bord achterloopt, dus de
		// onthouden staat van de werkkopieën gaat mee weg
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);

		$worktreeRepository->getAll()->each(
			fn (WorktreeEntity $worktreeEntity) => $worktreeRepository->forgetStatus($worktreeEntity->path),
		);

		StartFetchAction::execute();
	}

	/**
	 * The poll that keeps the board up to date while it is open.
	 */
	public function refreshWhenStale(): void
	{
		RefreshWhenStaleAction::execute();
	}

	/**
	 * Follows a running round; once it is done, every page on screen reads
	 * the new data.
	 */
	public function followRun(): void
	{
		$fetchRunEntity = GetLatestFetchRunAction::execute();

		if ($fetchRunEntity?->isRunning() ?? true) {
			return;
		}

		$this->dispatch('fetched');
	}

	/**
	 * The source that gave an error in the last run, by its name.
	 */
	private function failedSource(?FetchRunEntity $fetchRunEntity): ?string
	{
		$source = collect($fetchRunEntity?->results ?? [])->search('failed');

		return $source === false ? null : ucfirst((string) $source);
	}

	public function render(): View
	{
		$fetchRunEntity = GetLatestFetchRunAction::execute();

		return view('livewire.fetch-status', [
			'run' => $fetchRunEntity,
			'running' => $fetchRunEntity?->isRunning() ?? false,
			'connected' => CountConnectedSourcesAction::execute(),
			'updated' => $fetchRunEntity?->finishedAt?->format('H:i'),
			'failed' => $this->failedSource($fetchRunEntity),
			'lookEvery' => (int) config('devdock.refresh_seconds') > 0 ? self::LOOK_EVERY_SECONDS : 0,
		]);
	}
}
