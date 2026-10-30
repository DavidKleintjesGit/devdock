<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Objects\ChangeObjectCollection;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeEntityCollection;
use Src\Worktree\Objects\WorktreeId;
use Src\Worktree\Objects\WorktreeObject;

interface WorktreeRepositoryContract
{
	/**
	 * Lays down the working copy and writes it down. The project name is
	 * pinned in the folder, so time spent there lands on the project and not
	 * on the branch name.
	 *
	 * @throws WorktreeNotPossibleException
	 */
	public function create(WorktreeObject $worktreeObject): WorktreeEntity;

	public function getAll(): WorktreeEntityCollection;

	public function getFor(string $project, string $branch): ?WorktreeEntity;

	/**
	 * Writes down the address the working copy answers on, so it can be taken
	 * away again when the copy goes.
	 */
	public function pinSite(WorktreeId $worktreeId, ?string $site): WorktreeEntity;

	/**
	 * The folder where this branch already stands checked out, which is both
	 * the reason git refuses a second working copy and a perfectly good place
	 * to work.
	 */
	public function checkedOutAt(string $repositoryPath, string $branch): ?string;

	/**
	 * Puts the branch and its working copy under another name. Everything on
	 * a task is named after the ticket, so this is one move and not three.
	 *
	 * @throws WorktreeNotPossibleException
	 */
	public function rename(WorktreeId $worktreeId, string $branch): WorktreeEntity;

	/**
	 * Takes the working copy off disk and forgets it. Work that was never
	 * committed goes with it, so the caller decides when this is allowed.
	 */
	public function remove(WorktreeId $worktreeId): bool;

	/**
	 * Every file a round left behind in a working copy, with how much of it
	 * moved.
	 */
	public function getChanges(string $path): ChangeObjectCollection;

	/**
	 * The commit the branch stands on, which is what a review round holds for.
	 */
	/**
	 * Wat deze branch aan één bestand deed, tegen de hoofdlijn afgezet, of het
	 * nu al gecommit is of niet. Null als er niets te tonen valt.
	 */
	public function getDiff(string $path, string $file): ?string;

	public function getHead(string $path): ?string;

	/**
	 * Every file this branch changed against the main line, committed and
	 * uncommitted together. That is what a reviewer has to judge.
	 *
	 * @return array<int, string>
	 */
	public function getChangedFiles(string $path): array;

	/**
	 * Writes the standing changes down as one commit. Nothing leaves the
	 * machine; pushing is a step of its own.
	 */
	public function commit(string $path, string $title, string $body): bool;

	public function push(string $path): bool;

	/**
	 * Throws the standing state of a working copy away, for when something
	 * outside this repository changed what is in it. The next look reads it
	 * from git again.
	 */
	public function forgetStatus(string $path): void;
}
