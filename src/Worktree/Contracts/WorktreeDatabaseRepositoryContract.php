<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Src\Worktree\Enums\DatabaseFillEnum;
use Src\Worktree\Objects\DatabaseObject;

interface WorktreeDatabaseRepositoryContract
{
	/**
	 * Which database the working copy uses, and whether it is the one of the
	 * project itself.
	 */
	public function describe(string $repositoryPath, string $path): DatabaseObject;

	/**
	 * The databases on the same server the working copy could point at.
	 *
	 * @return array<int, string>
	 */
	public function getDatabases(string $path): array;

	public function exists(string $path, string $name): bool;

	/**
	 * Creates a database of its own for the working copy, fills it and points
	 * the .env at it. Returns why it failed, or null.
	 */
	public function create(string $repositoryPath, string $path, string $name, DatabaseFillEnum $fill): ?string;

	/**
	 * Points the working copy at a database that already exists.
	 */
	public function use(string $path, string $name): void;

	/**
	 * Throws away a database DevDock created for a working copy.
	 */
	public function drop(string $path, string $name): void;
}
