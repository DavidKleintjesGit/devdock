<?php

declare(strict_types=1);

namespace Src\Board\Actions;

use Src\Board\Enums\CleanUpEnum;
use Src\Board\Objects\CardCleanUpObject;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Actions\DescribeCleanUpAction;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * What cleaning up each of these cards takes away, and which work goes with
 * it, for you to read before you confirm.
 *
 * @method static list<CardCleanUpObject> execute(array $cards)
 */
class DescribeCardCleanUpAction extends Command
{
	/**
	 * @param  list<string>  $cards
	 */
	public function __construct(
		public readonly array $cards,
	) {}

	/**
	 * @return list<CardCleanUpObject>
	 */
	public function handle(WorktreeRepositoryContract $worktreeRepository): array
	{
		$cardObjects = collect(GetBoardAction::execute())->keyBy(fn (CardObject $cardObject): string => $cardObject->id);
		$worktreesByCard = collect(GetWorktreesAction::execute())
			->filter(fn (WorktreeEntity $worktreeEntity): bool => $worktreeEntity->card !== null)
			->keyBy(fn (WorktreeEntity $worktreeEntity): string => (string) $worktreeEntity->card);

		return array_values(array_map(function (string $card) use ($cardObjects, $worktreesByCard, $worktreeRepository): CardCleanUpObject {
			$cardObject = $cardObjects->get($card);
			$worktreeEntity = $worktreesByCard->get($card);

			return new CardCleanUpObject(
				card: $card,
				label: $cardObject?->ticketKey ?? ($cardObject?->isTodo() ? __('to-do') : $card),
				title: $cardObject?->title ?? $card,
				goes: $worktreeEntity === null ? [] : DescribeCleanUpAction::execute($worktreeEntity),
				workAtStake: $worktreeEntity === null ? '' : CleanUpEnum::workAtStake(
					$worktreeEntity->changedFiles,
					count($worktreeRepository->getUnpushedCommits($worktreeEntity->path)),
				),
				isTodo: $cardObject?->isTodo() ?? false,
			);
		}, $this->cards));
	}
}
