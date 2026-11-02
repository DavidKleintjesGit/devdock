<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Carbon\CarbonImmutable;
use Src\Packages\ValueObject\ValueObject;
use Src\Worktree\Enums\WorktreeOriginEnum;

/**
 * A working copy of one branch, standing apart from the checkout you work in
 * yourself.
 */
class WorktreeEntity extends ValueObject
{
	public function __construct(
		public readonly WorktreeId $worktreeId,
		public readonly string $project,
		public readonly string $branch,
		public readonly bool $branchCreated,
		public readonly string $path,
		public readonly WorktreeOriginEnum $origin,
		public readonly ?string $site,
		public readonly ?string $ownDatabase,
		public readonly ?string $card,
		public readonly int $changedFiles,
		public readonly int $ahead,
		// commits on the upstream that are not here, such as the version from before a rebase
		public readonly int $onlyOnUpstream,
		public readonly int $behind,
		public readonly ?string $upstream,
		public readonly string $mainBranch,
		public readonly ?CarbonImmutable $lastCommitAt,
		public readonly bool $dependenciesMissing,
		public readonly bool $hasCommits,
		public readonly ?CarbonImmutable $createdAt,
	) {
		parent::__construct();
	}

	/**
	 * Where this branch opens in the browser.
	 */
	public function url(): ?string
	{
		return $this->site === null ? null : 'http://' . $this->site;
	}

	/**
	 * Whether work is standing here that has not been committed or pushed.
	 */
	public function holdsWork(): bool
	{
		return $this->changedFiles > 0 || $this->ahead > 0;
	}

	/**
	 * What is waiting here, in words.
	 */
	public function waitingLabel(): ?string
	{
		$parts = array_filter([
			$this->changedFiles > 0
				? trans_choice(':count bestand gewijzigd|:count bestanden gewijzigd', $this->changedFiles, ['count' => $this->changedFiles])
				: null,
			$this->ahead > 0
				? trans_choice(':count commit nog niet gepusht|:count commits nog niet gepusht', $this->ahead, ['count' => $this->ahead])
				: null,
		]);

		return $parts === [] ? null : implode(' · ', $parts);
	}
}
