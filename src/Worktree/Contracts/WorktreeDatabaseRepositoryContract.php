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
	 * The database the tests of the working copy run in, from its .env.testing;
	 * null when they run on sqlite or there is no such file.
	 */
	public function describeTesting(string $repositoryPath, string $path): ?DatabaseObject;

	/**
	 * Creates a test database named after the branch, with the tables of the
	 * project's test database, and points the .env.testing of the working copy
	 * at it, so two copies never test in the same database. An own test database
	 * that is still empty gets the tables too. Returns why it failed, or null.
	 */
	public function giveOwnTestDatabase(string $repositoryPath, string $path, string $branch): ?string;

	/**
	 * The test database DevDock made for this working copy; null when it uses
	 * sqlite, the project's own test database, or one DevDock did not name.
	 */
	public function ownTestDatabase(string $repositoryPath, string $path): ?string;

	/**
	 * Throws away the test database DevDock made for this working copy, and
	 * no other.
	 */
	public function dropTestDatabase(string $repositoryPath, string $path): void;

	/**
	 * Throws away a database DevDock created for a working copy.
	 */
	public function drop(string $path, string $name): void;
}
