<?php

declare(strict_types=1);

namespace Src\Session\Actions;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Src\Board\Actions\BuildBoardAction;
use Src\Board\Enums\BoardColumnEnum;
use Src\Board\Objects\CardObject;
use Src\Packages\Cqrs\Command;
use Src\Project\Actions\GetProjectsAction;
use Src\Project\Objects\ProjectEntity;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Session\Enums\SessionTraitEnum;
use Src\Session\Objects\SessionEntity;
use Src\Worktree\Actions\GetWorktreesAction;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Every trait of every session, keyed by its uuid. The traits stack: the
 * page shows what has all of the ones you switched on.
 *
 * @method static array<string, array<int, SessionTraitEnum>> execute()
 */
class GetSessionTraitsAction extends Command
{
	/**
	 * A session that wrote to its file this recently is still going.
	 */
	private const RUNNING_MINUTES = 3;

	/**
	 * @return array<string, array<int, SessionTraitEnum>>
	 */
	public function handle(): array
	{
		$sessionEntities = collect(GetSessionsAction::execute());
		$cardObjects = collect(BuildBoardAction::execute());
		$projectEntities = collect(GetProjectsAction::execute())->keyBy('name');
		$worktreeEntities = collect(GetWorktreesAction::execute());

		$cardsBySession = [];

		foreach ($cardObjects as $cardObject) {
			foreach ($cardObject->sessions as $sessionEntity) {
				$cardsBySession[$sessionEntity->uuid] = $cardObject;
			}
		}

		$mergedBranches = collect(GetPullRequestsAction::execute())
			->filter(fn (PullRequestEntity $pullRequestEntity) => $pullRequestEntity->isMerged())
			->pluck('sourceBranch')
			->filter()
			->flip();

		$branchesByProject = $projectEntities->map(
			fn (ProjectEntity $projectEntity) => array_flip($this->localBranches($projectEntity->path)),
		);

		$latestByBranch = $sessionEntities
			->filter(fn (SessionEntity $sessionEntity) => filled($sessionEntity->branch) && ! in_array($sessionEntity->branch, ['master', 'main'], true))
			->groupBy(fn (SessionEntity $sessionEntity) => $sessionEntity->project . '/' . $sessionEntity->branch)
			->map(fn (Collection $group) => $group->max(fn (SessionEntity $sessionEntity) => $sessionEntity->lastActiveAt?->getTimestamp() ?? 0));

		$traits = [];

		foreach ($sessionEntities as $sessionEntity) {
			$traits[$sessionEntity->uuid] = $this->traitsOf(
				$sessionEntity,
				$cardsBySession[$sessionEntity->uuid] ?? null,
				$mergedBranches,
				$branchesByProject->get((string) $sessionEntity->project),
				$latestByBranch,
				$projectEntities,
				$worktreeEntities,
			);
		}

		return $traits;
	}

	/**
	 * The local branches of a checkout, read from its folder: asking git for
	 * every project would take seconds.
	 *
	 * @return array<int, string>
	 */
	private function localBranches(string $repositoryPath): array
	{
		$gitDirectory = $repositoryPath . '/.git';

		if (! is_dir($gitDirectory)) {
			return [];
		}

		$branches = [];
		$heads = $gitDirectory . '/refs/heads';

		if (is_dir($heads)) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($heads, FilesystemIterator::SKIP_DOTS));

			foreach ($iterator as $file) {
				if ($file->isFile()) {
					$branches[] = str_replace('\\', '/', substr($file->getPathname(), strlen($heads) + 1));
				}
			}
		}

		$packed = $gitDirectory . '/packed-refs';

		if (is_file($packed) && preg_match_all('#refs/heads/(\S+)#', (string) file_get_contents($packed), $found) > 0) {
			$branches = [...$branches, ...$found[1]];
		}

		return array_values(array_unique($branches));
	}

	/**
	 * @param  Collection<string, int>  $mergedBranches
	 * @param  array<string, int>|null  $branches
	 * @param  Collection<string, int>  $latestByBranch
	 * @param  Collection<string, ProjectEntity>  $projectEntities
	 * @param  Collection<int, WorktreeEntity>  $worktreeEntities
	 * @return array<int, SessionTraitEnum>
	 */
	private function traitsOf(
		SessionEntity $sessionEntity,
		?CardObject $cardObject,
		Collection $mergedBranches,
		?array $branches,
		Collection $latestByBranch,
		Collection $projectEntities,
		Collection $worktreeEntities,
	): array {
		$now = CarbonImmutable::now();
		$lastActiveAt = $sessionEntity->lastActiveAt ?? $now->subYears(10);
		$daysQuiet = (int) $lastActiveAt->diffInDays($now, absolute: true);
		$cwd = $sessionEntity->cwd === null ? null : rtrim($sessionEntity->cwd, '/');
		$isWorktree = $cwd !== null && str_contains($cwd, '/' . trim((string) config('devdock.worktree.folder'), '/') . '/');
		$cwdExists = $cwd !== null && is_dir($cwd);
		$branch = $sessionEntity->branch;
		$isRunning = $lastActiveAt->diffInMinutes($now, absolute: true) < self::RUNNING_MINUTES;

		$worktreeEntity = $cwd === null ? null : $worktreeEntities->first(
			fn (WorktreeEntity $worktreeEntity) => rtrim(str_replace('\\', '/', $worktreeEntity->path), '/') === $cwd,
		);
		$projectEntity = $projectEntities->get((string) $sessionEntity->project);
		$isProjectRoot = $projectEntity !== null && $cwd === rtrim(str_replace('\\', '/', $projectEntity->path), '/');

		$checks = [
			SessionTraitEnum::NO_TICKET->value => $cardObject === null,
			SessionTraitEnum::BRANCH_GONE->value => filled($branch) && $branches !== null && $cwdExists && ! isset($branches[$branch]),
			SessionTraitEnum::WORKTREE_GONE->value => $isWorktree && ! $cwdExists,
			SessionTraitEnum::MERGED->value => filled($branch) && $mergedBranches->has($branch),
			SessionTraitEnum::FOLLOWED_UP->value => filled($branch)
				&& ($latestByBranch[$sessionEntity->project . '/' . $branch] ?? 0) > ($sessionEntity->lastActiveAt?->getTimestamp() ?? 0),
			SessionTraitEnum::PROJECT_GONE->value => $sessionEntity->project === null || ($cwd !== null && ! $isWorktree && ! $cwdExists),
			SessionTraitEnum::UNNAMED->value => $sessionEntity->title === null,
			SessionTraitEnum::NAME_MISMATCH->value => $sessionEntity->mismatchedTicket() !== null,
			SessionTraitEnum::NOTHING_COMMITTED->value => ! $sessionEntity->hasCommit,
			SessionTraitEnum::ABANDONED->value => $sessionEntity->isInterrupted && ! $isRunning,
			SessionTraitEnum::UNANSWERED->value => $sessionEntity->isUnanswered && ! $isRunning,
			SessionTraitEnum::FALSE_START->value => $sessionEntity->promptCount <= (int) config('devdock.sessions.abandoned_up_to'),
			SessionTraitEnum::LARGE->value => $sessionEntity->isLarge,
			SessionTraitEnum::OLD->value => $daysQuiet >= (int) config('devdock.sessions.old_after_days'),
			SessionTraitEnum::OLDER_THAN_MONTH->value => $daysQuiet >= 30,
			SessionTraitEnum::OLDER_THAN_QUARTER->value => $daysQuiet >= 90,
			SessionTraitEnum::NEVER_RESUMED->value => $sessionEntity->activeDays <= 1 && $daysQuiet >= 1,
			SessionTraitEnum::RUNNING_NOW->value => $isRunning,
			SessionTraitEnum::OPEN_TASK->value => $cardObject !== null && $cardObject->column !== BoardColumnEnum::DONE,
			SessionTraitEnum::UNSAVED_WORK->value => ($worktreeEntity?->changedFiles ?? 0) > 0
				|| ($isProjectRoot && $projectEntity->branch === $branch && $projectEntity->changedFiles > 0),
			SessionTraitEnum::USED_THIS_WEEK->value => $daysQuiet < 7,
			SessionTraitEnum::PINNED->value => $sessionEntity->isPinned,
		];

		return array_values(array_map(
			fn (string $trait) => SessionTraitEnum::from($trait),
			array_keys(array_filter($checks)),
		));
	}
}
