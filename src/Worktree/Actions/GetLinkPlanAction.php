<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Board\Actions\BuildBoardAction;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\LinkStepEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\LinkPlanObject;
use Src\Worktree\Objects\SetupCheckObject;

/**
 * Per card on the board that is not done, what it still needs before an
 * agent can work on it: nothing, a repair of its working copy, a working copy
 * of its own, or first a branch.
 *
 * @method static list<LinkPlanObject> execute(?string $card = null)
 */
class GetLinkPlanAction extends Command
{
	public function __construct(
		public readonly ?string $card = null,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): array
	{
		return collect(BuildBoardAction::execute())
			->reject(fn (CardObject $cardObject): bool => $cardObject->isDone() || $cardObject->project === null)
			->filter(fn (CardObject $cardObject): bool => $this->card === null || $cardObject->id === $this->card)
			->map(fn (CardObject $cardObject): LinkPlanObject => $this->planFor($worktreeRepository, $cardObject))
			->values()
			->all();
	}

	private function planFor(WorktreeRepositoryContract $worktreeRepository, CardObject $cardObject): LinkPlanObject
	{
		if ($cardObject->branch === null) {
			return new LinkPlanObject($cardObject->id, $cardObject->project, null, LinkStepEnum::NEEDS_BRANCH, null, __('Nog geen branch.'));
		}

		$worktreeEntity = $worktreeRepository->getFor((string) $cardObject->project, $cardObject->branch);

		if ($worktreeEntity === null || $worktreeEntity->origin !== WorktreeOriginEnum::MADE || ! is_dir($worktreeEntity->path)) {
			return new LinkPlanObject(
				$cardObject->id,
				$cardObject->project,
				$cardObject->branch,
				LinkStepEnum::LINK,
				null,
				match (true) {
					$worktreeEntity === null => __('Nog geen werkkopie.'),
					$worktreeEntity->origin === WorktreeOriginEnum::PROJECT => __('Werkt in de checkout van het project zelf.'),
					default => __('Werkt in een map buiten DevDock: :path', ['path' => $worktreeEntity->path]),
				},
			);
		}

		$readinessObject = CheckReadinessAction::execute($worktreeEntity->path, true);

		return new LinkPlanObject(
			$cardObject->id,
			$cardObject->project,
			$cardObject->branch,
			$readinessObject->isReady() ? LinkStepEnum::READY : LinkStepEnum::REPAIR,
			$worktreeEntity->path,
			implode(', ', array_map(fn (SetupCheckObject $setupCheckObject): string => mb_strtolower($setupCheckObject->setupCheck->label()), $readinessObject->openChecks())),
		);
	}
}
