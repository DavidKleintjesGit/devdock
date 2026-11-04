<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\PullRequest\Actions\GetPullRequestsAction;
use Src\PullRequest\Objects\PullRequestEntity;
use Src\Ticket\Objects\TicketKey;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Objects\BranchChainObject;
use Src\Worktree\Objects\BranchLinkObject;

/**
 * Follows a branch down its stack of pull requests: the branch its pull request
 * merges into, the branch that one merges into, and so on to the main line,
 * with per step how far the branch is behind its target on origin.
 *
 * @method static BranchChainObject execute(string $repositoryPath, ?string $repositorySlug, string $branch, string $mainBranch)
 */
class GetBranchChainAction extends Command
{
	/**
	 * More steps than any stack holds; past it a loop in the targets ends the walk.
	 */
	private const MAX_LINKS = 12;

	public function __construct(
		public readonly string $repositoryPath,
		public readonly ?string $repositorySlug,
		public readonly string $branch,
		public readonly string $mainBranch,
	) {}

	public function handle(WorktreeRepositoryContract $worktreeRepository): BranchChainObject
	{
		$targetsBySource = collect(GetPullRequestsAction::execute())
			->filter(fn (PullRequestEntity $pullRequestEntity): bool => $pullRequestEntity->isOpen()
				&& $pullRequestEntity->repository === $this->repositorySlug
				&& $pullRequestEntity->sourceBranch !== null
				&& $pullRequestEntity->destinationBranch !== null)
			->mapWithKeys(fn (PullRequestEntity $pullRequestEntity): array => [(string) $pullRequestEntity->sourceBranch => (string) $pullRequestEntity->destinationBranch]);

		$links = [];
		$branch = $this->branch;

		while (count($links) < self::MAX_LINKS) {
			$target = $targetsBySource->get($branch) ?? ($branch === $this->branch ? $this->mainBranch : null);

			if ($target === null) {
				break;
			}

			$links[] = new BranchLinkObject(
				branch: $branch,
				target: $target,
				// the task's own branch counts as it stands here, so a rebase that is not pushed yet already counts
				behind: $worktreeRepository->countBehind($this->repositoryPath, $branch, $target, asHere: $branch === $this->branch),
				cardKey: TicketKey::ofBranch($branch),
			);

			if ($target === $this->mainBranch) {
				break;
			}

			$branch = $target;
		}

		return new BranchChainObject($links, $this->mainBranch);
	}
}
