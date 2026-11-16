<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithCodeReader;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Board\Actions\GetBoardAction;
use Src\Board\Objects\CardObject;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DiffViewEnum;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The changes of one card in a window of their own, to read beside the board.
 * The window keeps what it shows until you renew it, and says when the
 * working copy changed since.
 */
class Changes extends Component
{
	use WithCodeReader;

	public string $card = '';

	#[Url(as: 'view')]
	public ?string $diffView = null;

	#[Url(as: 'run')]
	public ?int $diffRun = null;

	/** The state of the working copy when the window last read it. */
	public ?string $fingerprint = null;

	public function mount(string $card): void
	{
		$this->card = $card;
		$worktreeEntity = $this->worktree();
		$this->fingerprint = $worktreeEntity === null ? null : resolve(WorktreeRepositoryContract::class)->getFingerprint($worktreeEntity->path, isFresh: true);
	}

	/**
	 * Whether the working copy moved since the window read it. The window asks
	 * without drawing anything again, so what you read stays put.
	 */
	#[Renderless]
	public function lookForChanges(): bool
	{
		$worktreeEntity = $this->worktree();

		return $worktreeEntity !== null
			&& resolve(WorktreeRepositoryContract::class)->getFingerprint($worktreeEntity->path, isFresh: true) !== $this->fingerprint;
	}

	/**
	 * Reads the changes again.
	 */
	public function renew(): void
	{
		$worktreeEntity = $this->worktree();

		if ($worktreeEntity !== null) {
			resolve(WorktreeRepositoryContract::class)->forgetStatus($worktreeEntity->path);
			$this->fingerprint = resolve(WorktreeRepositoryContract::class)->getFingerprint($worktreeEntity->path, isFresh: true);
		}

	}

	public function setDiffView(string $view): void
	{
		$this->diffView = DiffViewEnum::tryFrom($view)?->value ?? $this->diffView;
	}

	private function cardObject(): ?CardObject
	{
		return collect(GetBoardAction::execute())->first(fn (CardObject $cardObject): bool => $cardObject->id === $this->card);
	}

	private function worktree(?CardObject $cardObject = null): ?WorktreeEntity
	{
		$cardObject ??= $this->cardObject();

		if ($cardObject?->project === null || $cardObject->branch === null) {
			return null;
		}

		return GetWorktreesAction::execute()->first(
			fn (WorktreeEntity $worktreeEntity): bool => $worktreeEntity->project === $cardObject->project && $worktreeEntity->branch === $cardObject->branch,
		);
	}

	public function render(): View
	{
		$cardObject = $this->cardObject();
		$worktreeEntity = $this->worktree($cardObject);
		$diffView = $worktreeEntity === null ? DiffViewEnum::BRANCH : $this->diffViewFor($worktreeEntity);
		$diffTree = $worktreeEntity === null ? [] : $this->diffTreeOf($worktreeEntity, $diffView);
		$diffFiles = $worktreeEntity === null ? [] : $this->stackedDiffs($worktreeEntity, $diffView, $diffTree);

		return view('livewire.changes', [
			'cardObject' => $cardObject,
			'hasWorktree' => $worktreeEntity !== null,
			'reader' => [
				'card' => $cardObject,
				'worktree' => $worktreeEntity,
				'diffView' => $diffView,
				'diffTree' => $diffTree,
				'diffFiles' => $diffFiles,
				'diffFingerprints' => collect($diffFiles)->mapWithKeys(fn (array $diffFile) => [$diffFile['change']->path => $diffFile['fingerprint']])->all(),
				'diffAdded' => collect($diffFiles)->sum(fn (array $diffFile) => $diffFile['change']->added),
				'diffRemoved' => collect($diffFiles)->sum(fn (array $diffFile) => $diffFile['change']->removed),
				'diffViews' => $worktreeEntity === null ? [] : $this->diffViewCounts($worktreeEntity),
				'roundRun' => $this->roundRun(),
				'unpushedCount' => $worktreeEntity === null ? 0 : resolve(WorktreeRepositoryContract::class)->getNewCommitCount($worktreeEntity->path),
				'openDiffFile' => null,
			],
		])->layout('layouts.window', ['title' => __('Wijzigingen') . ' · ' . ($cardObject?->ticketKey ?? $cardObject?->title ?? $this->card)]);
	}
}
