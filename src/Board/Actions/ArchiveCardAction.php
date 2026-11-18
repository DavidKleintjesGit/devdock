<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Contracts\CardArchiveRepositoryContract;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;

/**
 * Cleans up a task and takes its card off the board. It comes back on its
 * own once its ticket or pull request changes. Returns why it did not
 * happen, or null.
 *
 * @method static ?string execute(string $card, bool $discardsWork = false)
 */
class ArchiveCardAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly bool $discardsWork = false,
	) {}

	public function handle(CardArchiveRepositoryContract $cardArchiveRepository): ?string
	{
		$cardObject = GetBoardAction::execute()->first(fn (CardObject $cardObject): bool => $cardObject->id === $this->card);
		$failure = CleanUpCardAction::execute($this->card, $this->discardsWork);

		if ($failure !== null) {
			return $failure;
		}

		$cardArchiveRepository->archive($this->card, $cardObject?->resurfaceSignature() ?? '');

		return null;
	}
}
