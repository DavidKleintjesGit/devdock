<?php

declare(strict_types=1);

namespace Src\Worktree\Actions;

use Src\Packages\Cqrs\Command;
use Src\Worktree\Contracts\WorktreeDatabaseRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Objects\WorktreeEntity;

/**
 * What cleaning up a working copy takes away, one line per thing, so you
 * read it before you press the button.
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
	public function handle(WorktreeDatabaseRepositoryContract $worktreeDatabaseRepository): array
	{
		$worktreeEntity = $this->worktreeEntity;
		$testDatabase = $worktreeEntity->origin->isOurs() && is_dir($worktreeEntity->path)
			? $worktreeDatabaseRepository->ownTestDatabase(dirname($worktreeEntity->path, 2), $worktreeEntity->path)
			: null;

		return array_values(array_filter([
			match ($worktreeEntity->origin) {
				WorktreeOriginEnum::MADE => __('De werkkopie :path', ['path' => $worktreeEntity->path]),
				WorktreeOriginEnum::ADOPTED => __('Niet de map: die stond er al, alleen de koppeling gaat weg'),
				WorktreeOriginEnum::PROJECT => __('Niet het project zelf: alleen de koppeling gaat weg'),
			},
			$worktreeEntity->origin === WorktreeOriginEnum::MADE ? __('Processen die nog in de werkkopie draaien, zoals een queue of een testrun; die worden eerst gestopt') : null,
			$worktreeEntity->ownDatabase === null ? null : __('De eigen database :name', ['name' => $worktreeEntity->ownDatabase]),
			$testDatabase === null ? null : __('De eigen testdatabase :name', ['name' => $testDatabase]),
			$worktreeEntity->site === null ? null : __('Het adres :site in Herd', ['site' => $worktreeEntity->site]),
			$worktreeEntity->origin->isOurs() ? __('De lokale branch :branch; op origin blijft hij staan', ['branch' => $worktreeEntity->branch]) : null,
		]));
	}
}
