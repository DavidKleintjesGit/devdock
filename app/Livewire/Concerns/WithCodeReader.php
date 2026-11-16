<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;
use Src\Worktree\Actions\BuildFileTreeAction;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DiffViewEnum;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\ChangeObjectCollection;
use Src\Worktree\Objects\FileTreeNodeObject;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * The code reader: which changes of a working copy it shows, as a tree of
 * folders and as the files under each other. A component that reads code
 * keeps the picked view in $diffView and the turn of a round view in $diffRun.
 */
trait WithCodeReader
{
	/**
	 * Lines from where a file opens closed, so the page stays quick.
	 */
	private const LARGE_DIFF_LINES = 600;

	/**
	 * The changes the tab and the code reader show: the one you picked, or else
	 * what is not committed, what is not pushed, the whole branch, in that order.
	 */
	protected function diffViewFor(WorktreeEntity $worktreeEntity): DiffViewEnum
	{
		$picked = DiffViewEnum::tryFrom((string) $this->diffView);

		// a round needs a turn with both snapshots; without one the reader shows the whole branch
		if ($picked !== null && ($picked !== DiffViewEnum::ROUND || $this->roundRun() !== null)) {
			return $picked;
		}

		$counts = $this->diffViewCounts($worktreeEntity);

		return match (true) {
			$counts[DiffViewEnum::UNCOMMITTED->value] > 0 => DiffViewEnum::UNCOMMITTED,
			$counts[DiffViewEnum::UNPUSHED->value] > 0 => DiffViewEnum::UNPUSHED,
			default => DiffViewEnum::BRANCH,
		};
	}

	/**
	 * How many files each view holds, by the view.
	 *
	 * @return array<string, int>
	 */
	protected function diffViewCounts(WorktreeEntity $worktreeEntity): array
	{
		$counts = [];

		foreach (DiffViewEnum::cases() as $diffView) {
			$counts[$diffView->value] = count($this->viewChanges($worktreeEntity, $diffView));
		}

		return $counts;
	}

	/**
	 * The changed files of one view of the code reader; a round reads between the snapshots of its turn.
	 */
	protected function viewChanges(WorktreeEntity $worktreeEntity, DiffViewEnum $diffView): ChangeObjectCollection
	{
		$roundRun = $diffView === DiffViewEnum::ROUND ? $this->roundRun() : null;

		return match (true) {
			$diffView !== DiffViewEnum::ROUND => resolve(WorktreeRepositoryContract::class)->getChangesFor($worktreeEntity->path, $diffView),
			$roundRun === null => ChangeObjectCollection::fromArray([]),
			default => resolve(WorktreeRepositoryContract::class)->getChangesBetween($worktreeEntity->path, (string) $roundRun->treeBefore, (string) $roundRun->treeAfter),
		};
	}

	/**
	 * The turn the round view reads, when it kept both snapshots.
	 */
	protected function roundRun(): ?PromptRunEntity
	{
		$promptRunEntity = $this->diffRun === null ? null : resolve(PromptRunRepositoryContract::class)->getById(new PromptRunId(id: $this->diffRun));

		return $promptRunEntity?->hasSnapshots() === true ? $promptRunEntity : null;
	}

	/**
	 * The changed files of one view as a tree of folders. A new folder is
	 * opened up into its files, so each one has its own line and its own count.
	 *
	 * @return array<int, FileTreeNodeObject>
	 */
	protected function diffTreeOf(WorktreeEntity $worktreeEntity, DiffViewEnum $diffView): array
	{
		return BuildFileTreeAction::execute(collect($this->viewChanges($worktreeEntity, $diffView))->values()->all());
	}

	/**
	 * Every changed file of one view, in the order of the tree, for the page
	 * that shows them under each other. Each file reads its own changes once
	 * it comes into view.
	 *
	 * @param  array<int, FileTreeNodeObject>  $diffTree
	 * @return array<int, array{change: ChangeObject, fingerprint: string, isLarge: bool, isGenerated: bool}>
	 */
	protected function stackedDiffs(WorktreeEntity $worktreeEntity, DiffViewEnum $diffView, array $diffTree): array
	{
		$worktreeRepository = resolve(WorktreeRepositoryContract::class);
		$changes = collect($this->viewChanges($worktreeEntity, $diffView))->keyBy('path');
		$generatedFiles = $worktreeRepository->getGeneratedFiles($worktreeEntity->path, $changes->keys()->all());
		$files = [];

		foreach (array_merge([], ...array_map(fn (FileTreeNodeObject $node) => $node->filePaths(), $diffTree)) as $path) {
			$changeObject = $changes->get($path);

			if ($changeObject === null) {
				continue;
			}

			$wholePath = $worktreeEntity->path . '/' . $path;

			$files[] = [
				'change' => $changeObject,
				// read without git, so the page asks for no patch until a file is in view; a new save of the file un-views it
				'fingerprint' => md5(implode(':', [$changeObject->added, $changeObject->removed, @filemtime($wholePath) ?: 0, @filesize($wholePath) ?: 0])),
				// a file this large opens closed, so the page stays quick; opening it shows it in full
				'isLarge' => $changeObject->added + $changeObject->removed > self::LARGE_DIFF_LINES,
				'isGenerated' => in_array($path, $generatedFiles, true),
			];
		}

		return $files;
	}
}
