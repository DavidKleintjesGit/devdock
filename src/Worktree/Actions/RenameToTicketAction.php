<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Task\Actions\ActivateLinkAction;
use Src\Task\Actions\LinkToTaskAction;
use Src\Task\Actions\UnlinkFromTaskAction;
use Src\Task\Enums\TaskLinkKindEnum;
use Src\Worktree\Contracts\WorkingCopyRepositoryContract;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Objects\SiteName;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * Puts a branch, its working copy and the pinned tie under one name. Branch,
 * pull request and ticket belong together through the ticket number, so a
 * name that does not carry it is corrected in one move.
 *
 * @method static WorktreeEntity execute(string $card, string $from, string $to)
 *
 * @throws WorktreeNotPossibleException
 */
class RenameToTicketAction extends Command
{
	public function __construct(
		public readonly string $card,
		public readonly string $from,
		public readonly string $to,
	) {}

	public function handle(
		WorktreeRepositoryContract $worktreeRepository,
		WorkingCopyRepositoryContract $workingCopyRepository,
	): WorktreeEntity {
		$worktreeEntity = $worktreeRepository->getAll()->first(
			fn (WorktreeEntity $worktreeEntity) => $worktreeEntity->branch === $this->from,
		);

		if ($worktreeEntity === null) {
			throw new WorktreeNotPossibleException(__('De branch :branch heeft geen werkkopie om te hernoemen.', [
				'branch' => $this->from,
			]));
		}

		$repositoryPath = dirname($worktreeEntity->path, 2);

		// git moves the folder itself, and it would walk straight into the
		// borrowed packages on the way
		$workingCopyRepository->release($worktreeEntity->path, $worktreeEntity->site);

		$renamed = $worktreeRepository->rename($worktreeEntity->worktreeId, $this->to);

		$host = $workingCopyRepository->prepare(
			$repositoryPath,
			$renamed->path,
			SiteName::for($renamed->project, $this->to),
		);

		// the task was tied to the old name, so the tie moves with it
		UnlinkFromTaskAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->from);
		LinkToTaskAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->to);
		ActivateLinkAction::execute($this->card, TaskLinkKindEnum::BRANCH, $this->to);

		return $worktreeRepository->pinSite($renamed->worktreeId, $host);
	}
}
