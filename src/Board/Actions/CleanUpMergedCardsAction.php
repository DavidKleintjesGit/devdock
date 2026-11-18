<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Cleans up every task whose pull request was merged and that still has a
 * working copy, as long as no work is lost with it. A task with work that
 * origin does not hold stays as it is, and its card says so. Returns how
 * many tasks were cleaned up.
 *
 * @method static int execute()
 */
class CleanUpMergedCardsAction extends Command
{
	public function handle(): int
	{
		$cardsWithCopy = collect(GetWorktreesAction::execute())
			->map(fn (WorktreeEntity $worktreeEntity): ?string => $worktreeEntity->card)
			->filter()
			->all();

		$mergedCards = collect(GetBoardAction::execute(true))
			->filter(fn (CardObject $cardObject): bool => $cardObject->column === BoardColumnEnum::DONE
				&& $cardObject->pullRequests->isNotEmpty()
				&& in_array($cardObject->id, $cardsWithCopy, true));

		return $mergedCards
			->filter(fn (CardObject $cardObject): bool => CleanUpCardAction::execute($cardObject->id) === null)
			->count();
	}
}
