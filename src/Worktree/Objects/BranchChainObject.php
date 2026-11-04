<?php

declare(strict_types=1);

namespace Src\Worktree\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * The stack a branch stands in, from the branch itself down to the main line:
 * each pull request and the branch it merges into.
 */
class BranchChainObject extends ValueObject
{
	/**
	 * @param  list<BranchLinkObject>  $links  the branch's own link first, the one on the main line last
	 */
	public function __construct(
		public readonly array $links,
		public readonly string $mainBranch,
	) {
		parent::__construct();
	}

	/**
	 * Whether the branch stands on another pull request rather than on the main line.
	 */
	public function isStacked(): bool
	{
		return count($this->links) > 1;
	}

	/**
	 * The lowest link below the branch that is behind its own target. That one
	 * is brought level first; moving this branch before it would build on a
	 * stand that is about to change.
	 */
	public function blockingLink(): ?BranchLinkObject
	{
		$below = array_reverse(array_slice($this->links, 1));

		foreach ($below as $branchLinkObject) {
			if ($branchLinkObject->behind > 0) {
				return $branchLinkObject;
			}
		}

		return null;
	}

	/**
	 * The links from the one on the main line up to the branch itself, in the order the stack is built.
	 *
	 * @return list<BranchLinkObject>
	 */
	public function linksFromMain(): array
	{
		return array_reverse($this->links);
	}
}
