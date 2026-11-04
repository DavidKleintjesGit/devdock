<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Access\Actions\GetRoleAccessAction;
use Src\Access\Enums\AgentRoleEnum;
use Src\Prompt\Contracts\ClaudeProcessRepositoryContract;
use Src\Session\Actions\DeleteSessionAction;
use Src\Session\Actions\GetSessionsAction;
use Src\Session\Actions\GetTranscriptAction;
use Src\Session\Actions\PinSessionAction;
use Src\Session\Actions\RenameSessionAction;
use Src\Session\Enums\SessionTraitEnum;
use Src\Session\Objects\SessionEntity;
use Src\Session\Objects\SessionId;

/**
 * The actions that belong to a session panel. They live in a trait because
 * both the board and the sessions page can show that same panel.
 */
trait WithSessionActions
{
	/**
	 * How many traits it takes before a session that nothing protects is
	 * worth suggesting for removal.
	 */
	private const TRAITS_TO_GO = 3;

	public bool $actionsOpen = false;

	public bool $confirmingDelete = false;

	public bool $renaming = false;

	public string $newTitle = '';

	public bool $wholeTranscript = false;

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

	public function pin(string $uuid, bool $isPinned): void
	{
		$sessionEntity = $this->sessionByUuid($uuid);

		if ($sessionEntity !== null) {
			PinSessionAction::execute($sessionEntity->sessionId, $isPinned);
		}

		$this->actionsOpen = false;
	}

	/**
	 * Opens Claude Code on this session, in the folder it ran in.
	 */
	public function resume(string $uuid): void
	{
		$sessionEntity = $this->sessionByUuid($uuid);

		if ($sessionEntity?->cwd !== null && is_dir($sessionEntity->cwd)) {
			resolve(ClaudeProcessRepositoryContract::class)->openInTerminal(GetRoleAccessAction::execute(AgentRoleEnum::AGENT), $sessionEntity->cwd, $sessionEntity->uuid, []);
		}
	}

	/**
	 * Everything the panel of one session shows.
	 *
	 * @param  array<int, SessionTraitEnum>  $traits
	 * @return array<string, mixed>
	 */
	protected function sessionDrawer(SessionEntity $sessionEntity, array $traits, ?string $previous = null): array
	{
		$transcript = collect(GetTranscriptAction::execute($sessionEntity->sessionId));

		return [
			'type' => 'session',
			'session' => $sessionEntity,
			'traits' => $traits,
			'verdict' => $this->verdictFor($traits),
			'transcript' => $this->wholeTranscript || $transcript->count() <= 4 ? $transcript : $transcript->take(2)->concat($transcript->take(-2)),
			'hiddenTurns' => $this->wholeTranscript ? 0 : max(0, $transcript->count() - 4),
			'size' => $this->megabytes($sessionEntity->bytes),
			'previous' => $previous,
		];
	}

	/**
	 * Whether the session can go, in a title and a line, and the colour that
	 * goes with it.
	 *
	 * @param  array<int, SessionTraitEnum>  $traits
	 * @return array{title: string, text: string, tone: string}
	 */
	protected function verdictFor(array $traits): array
	{
		$protecting = collect($traits)->filter(fn (SessionTraitEnum $trait) => $trait->protects());
		$away = count($traits) - $protecting->count();

		return match (true) {
			$protecting->isNotEmpty() => [
				'title' => __('Bewaren'),
				'text' => __('Hij is beschermd: :traits.', ['traits' => mb_strtolower($protecting->map(fn (SessionTraitEnum $trait) => $trait->label())->implode(', '))]),
				'tone' => 'green',
			],
			$away >= self::TRAITS_TO_GO => [
				'title' => __('Deze kan weg'),
				'text' => trans_choice(':count kenmerk wijst die kant op, en niets pleit ertegen.|:count kenmerken wijzen die kant op, en niets pleit ertegen.', $away, ['count' => $away]),
				'tone' => 'red',
			],
			default => [
				'title' => __('Twijfel'),
				'text' => __('Er wijst weinig op dat hij weg kan, maar er pleit ook niets voor bewaren.'),
				'tone' => 'gray',
			],
		};
	}

	protected function megabytes(int $bytes): string
	{
		$decimal = app()->getLocale() === 'nl' ? ',' : '.';

		return $bytes >= 1073741824
			? number_format($bytes / 1073741824, 1, $decimal, '') . ' GB'
			: number_format($bytes / 1048576, $bytes >= 104857600 ? 0 : 1, $decimal, '') . ' MB';
	}

	private function sessionByUuid(string $uuid): ?SessionEntity
	{
		return GetSessionsAction::execute()
			->filter(fn (SessionEntity $sessionEntity) => $sessionEntity->uuid === $uuid)
			->first();
	}
}
