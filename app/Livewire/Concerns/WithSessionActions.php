<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Session\Actions\DeleteSessionAction;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Actions\RenameSessionAction;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionId;

/**
 * The actions that belong to a session panel. They live in a trait because
 * both the board and the sessions page can show that same panel.
 */
trait WithSessionActions
{
	public bool $actionsOpen = false;

	public bool $confirmingDelete = false;

	public bool $renaming = false;

	public string $newTitle = '';

	public function toggleActions(): void
	{
		$this->actionsOpen = ! $this->actionsOpen;
		$this->confirmingDelete = false;
	}

	/**
	 * Renaming asks for a name, so a window belongs with it. The name the board
	 * proposes is already in it.
	 */
	public function startRename(string $uuid): void
	{
		$sessionEntity = $this->sessionByUuid($uuid);

		if ($sessionEntity === null) {
			return;
		}

		$this->newTitle = $sessionEntity->proposedTitle() ?? $sessionEntity->name();
		$this->renaming = true;
		$this->actionsOpen = false;
	}

	public function cancelRename(): void
	{
		$this->renaming = false;
	}

	public function rename(string $uuid): void
	{
		$this->validate(['newTitle' => ['required', 'min:3']], [
			'newTitle.required' => __('Geef de sessie een naam.'),
			'newTitle.min' => __('Geef de sessie een naam.'),
		]);

		RenameSessionAction::execute(new SessionId(uuid: $uuid), $this->newTitle);

		// the panel stays open; you are still busy with the same session
		$this->renaming = false;
	}

	/**
	 * Deleting is the one case where the panel does close: what you were looking
	 * at is gone.
	 */
	public function deleteSession(string $uuid): void
	{
		if (! $this->confirmingDelete) {
			$this->confirmingDelete = true;

			return;
		}

		DeleteSessionAction::execute(new SessionId(uuid: $uuid));

		$this->confirmingDelete = false;
		$this->actionsOpen = false;
		$this->back();
	}

	private function sessionByUuid(string $uuid): ?SessionEntity
	{
		return GetSessionsAction::execute()
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->uuid === $uuid)
			->first();
	}
}
