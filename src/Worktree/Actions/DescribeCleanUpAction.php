<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Contracts\ReviewRoundRepositoryContract;
use Src\Session\Contracts\SessionFileRepositoryContract;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * What cleaning up a task takes away, one line per thing, so you read it
 * before you press the button.
 *
 * @method static list<string> execute(WorktreeEntity $worktreeEntity)
 */
class DescribeCleanUpAction extends Command
{
	public function __construct(
		public readonly WorktreeEntity $worktreeEntity,
	) {}

	/**
	 * @return list<string>
	 */
	public function handle(
		SessionFileRepositoryContract $sessionFileRepository,
		ReviewRoundRepositoryContract $reviewRoundRepository,
		PromptRunRepositoryContract $promptRunRepository,
		AgentContextRepositoryContract $agentContextRepository,
		WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository,
	): array {
		$worktreeEntity = $this->worktreeEntity;
		$testDatabase = $worktreeEntity->origin->isOurs() && is_dir($worktreeEntity->path)
			? $worktreeDatabaseRepository->ownTestDatabase(dirname($worktreeEntity->path, 2), $worktreeEntity->path)
			: null;
		$sessions = $worktreeEntity->origin->isOurs() ? $sessionFileRepository->countFor($worktreeEntity->path) : 0;
		$rounds = $worktreeEntity->card === null ? 0 : $reviewRoundRepository->countLogsForCard($worktreeEntity->card);
		$agentRounds = $worktreeEntity->card === null ? 0 : $promptRunRepository->countLogsForCard($worktreeEntity->card);

		return array_values(array_filter([
			match ($worktreeEntity->origin) {
				WorktreeOriginEnum::MADE => __('De werkkopie :path', ['path' => $worktreeEntity->path]),
				WorktreeOriginEnum::ADOPTED => __('Niet de map: die stond er al, alleen de koppeling gaat weg'),
				WorktreeOriginEnum::PROJECT => __('Niet het project zelf: alleen de koppeling gaat weg'),
			},
			$worktreeEntity->ownDatabase === null ? null : __('De eigen database :name', ['name' => $worktreeEntity->ownDatabase]),
			$testDatabase === null ? null : __('De eigen testdatabase :name', ['name' => $testDatabase]),
			$worktreeEntity->site === null ? null : __('Het adres :site in Herd', ['site' => $worktreeEntity->site]),
			$worktreeEntity->origin->isOurs() && $worktreeEntity->branchCreated
				? __('De branch :branch, als de hoofdlijn hem helemaal bevat', ['branch' => $worktreeEntity->branch])
				: null,
			$sessions === 0 ? null : trans_choice(':count Claude-sessie van deze werkkopie|:count Claude-sessies van deze werkkopie', $sessions, ['count' => $sessions]),
			$rounds === 0 ? null : trans_choice('De logboeken van :count lensronde|De logboeken van :count lensrondes', $rounds, ['count' => $rounds]),
			$agentRounds === 0 ? null : trans_choice('De logboeken van :count agentronde|De logboeken van :count agentrondes', $agentRounds, ['count' => $agentRounds]),
			$worktreeEntity->card !== null && $agentContextRepository->hasReports($worktreeEntity->path, $worktreeEntity->card)
				? __('De rondverslagen in :folder', ['folder' => dirname($agentContextRepository->reportFolder($worktreeEntity->card, 1))])
				: null,
		]));
	}
}
