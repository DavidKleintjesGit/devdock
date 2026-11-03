<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Src\Worktree\Objects\BranchStandingObject;

interface BranchStandingRepositoryContract
{
	/**
	 * How a branch of the repository stands against the main line on origin,
	 * by its local commits when it has them and else by what origin holds.
	 */
	public function getFor(string $repositoryPath, string $branch): BranchStandingObject;

	/**
	 * The standing from the last read, without asking git: null when it was
	 * never read. The branch is noted, so the next refresh reads it.
	 */
	public function getKnown(string $repositoryPath, string $branch): ?BranchStandingObject;

	/**
	 * Reads the branches that are not in the cache yet side by side, so a
	 * board with many tasks does not wait on git one branch at a time.
	 *
	 * @param  array<int, string>  $branches
	 */
	public function warm(string $repositoryPath, array $branches, ?int $keepSeconds = null, bool $again = false): void;

	/**
	 * Fetches the main line and reads every branch asked for before again,
	 * so a page finds them ready.
	 */
	public function refresh(int $keepSeconds): void;

	public function forget(string $repositoryPath, string $branch): void;
}
