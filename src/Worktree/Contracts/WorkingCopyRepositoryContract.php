<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Src\Worktree\Objects\SiteName;

interface WorkingCopyRepositoryContract
{
	/**
	 * Puts everything in a fresh working copy that git does not carry: the
	 * heavy folders are borrowed from the checkout, the ignored files copied,
	 * and the copy gets an address of its own.
	 *
	 * Returns the host it now answers on, or null when there is no Herd to
	 * serve it.
	 */
	public function prepare(string $repositoryPath, string $path, SiteName $siteName): ?string;

	/**
	 * Takes the borrowed folders and the address away again. This runs before
	 * the copy itself is moved or deleted: removing a folder that is only a
	 * link to the checkout would otherwise take the real one with it.
	 */
	public function release(string $path, ?string $site): void;
}
