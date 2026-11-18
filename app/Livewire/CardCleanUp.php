<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Src\Board\Actions\DescribeCardCleanUpAction;
use Src\Board\Enums\CardActionEnum;
use Src\Board\Objects\CardCleanUpObject;
use Src\Worktree\Actions\StartWorkingCopyTaskAction;
use Src\Worktree\Enums\WorkingCopyTaskEnum;
use Src\Worktree\Objects\WorkingCopyTaskObject;

/**
 * The confirmation before cards are cleaned up, archived or deleted: per card
 * what goes, and the work that would be lost, which you accept apart. Each
 * card then goes in a process of its own.
 */
class CardCleanUp extends Component
{
	/** @var list<string> */
	public array $cards = [];

	public string $action = '';

	public bool $acceptsLoss = false;

	public ?string $failure = null;

	/**
	 * @param  list<string>  $cards
	 */
	#[On('clean-up-cards')]
	public function open(array $cards, string $action): void
	{
		$this->cards = array_values(array_unique($cards));
		$this->action = CardActionEnum::from($action)->value;
		$this->acceptsLoss = false;
		$this->failure = null;
	}

	public function close(): void
	{
		$this->cards = [];
	}

	public function confirm(): void
	{
		$cardActionEnum = CardActionEnum::from($this->action);
		$cardCleanUpObjects = collect(DescribeCardCleanUpAction::execute($this->cards));

		if (! $this->acceptsLoss && $cardCleanUpObjects->contains(fn (CardCleanUpObject $cardCleanUpObject): bool => $cardCleanUpObject->losesWork())) {
			$this->failure = __('Vink aan dat dit werk verloren mag gaan, of laat die kaarten staan.');

			return;
		}

		foreach ($cardCleanUpObjects as $cardCleanUpObject) {
			StartWorkingCopyTaskAction::execute(new WorkingCopyTaskObject(
				card: $cardCleanUpObject->card,
				task: match (true) {
					$cardActionEnum === CardActionEnum::DELETE && $cardCleanUpObject->isTodo => WorkingCopyTaskEnum::DELETE,
					$cardActionEnum === CardActionEnum::CLEAN_UP => WorkingCopyTaskEnum::CLEAN_UP,
					default => WorkingCopyTaskEnum::ARCHIVE,
				},
				project: '',
				branch: '',
				discardsWork: $this->acceptsLoss,
			));
		}

		$this->dispatch('cards-cleaned-up', cards: $this->cards, action: $cardActionEnum->value);
		$this->cards = [];
	}

	public function render(): View
	{
		if ($this->cards === []) {
			return view('livewire.card-clean-up', ['rows' => []]);
		}

		$rows = DescribeCardCleanUpAction::execute($this->cards);

		return view('livewire.card-clean-up', [
			'rows' => $rows,
			'cardAction' => CardActionEnum::from($this->action),
			'losesWork' => collect($rows)->contains(fn (CardCleanUpObject $cardCleanUpObject): bool => $cardCleanUpObject->losesWork()),
		]);
	}
}
