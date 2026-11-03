<?php

declare(strict_types=1);

namespace Src\Worktree\Contracts;

use Carbon\CarbonImmutable;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Objects\ChangeObjectCollection;
use Src\Worktree\Objects\RebaseObject;
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

	/**
	 * Writes down the database DevDock made for this copy, so it can go along
	 * when the copy is cleaned up.
	 */
	public function markDatabase(WorktreeId $worktreeId, ?string $database): WorktreeEntity;

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
	 * Every branch that stands checked out somewhere, with the folder it
	 * stands in: the project itself or one of its working copies.
	 *
	 * @return array<string, string>
	 */
	public function getPlaces(string $repositoryPath): array;

	/**
	 * Writes down a folder DevDock did not make as the working copy of a
	 * branch: one that already stood there, or the project itself. Cleaning
	 * up leaves such a folder where it is.
	 */
	public function adopt(string $project, string $branch, string $path, WorktreeOriginEnum $origin, ?string $card): WorktreeEntity;

	/**
	 * Whether the main line on origin already holds every commit of this
	 * branch, so taking the branch away loses nothing.
	 */
	public function isMerged(string $repositoryPath, string $branch): bool;

	/**
	 * Puts the project itself back on the main line, so the branch it stood on
	 * can get a working copy of its own. Uncommitted work refuses the switch,
	 * unless it is to be carried: then it goes into a stash for layBackCarried.
	 * Gives back why it did not work, or null.
	 */
	public function switchToMain(string $repositoryPath, bool $carryChanges = false): ?string;

	/**
	 * The tracked files with changes that are not committed yet.
	 *
	 * @return array<int, string>
	 */
	public function getUncommittedFiles(string $repositoryPath): array;

	/**
	 * Lays the work switchToMain set aside back down in a working copy.
	 * Gives back why it did not work, or null.
	 */
	public function layBackCarried(string $path): ?string;

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
	 * The branch the folder stands on; null when it stands on a bare commit.
	 */
	public function getBranch(string $path): ?string;

	/**
	 * A hash of everything this branch changed against the main line,
	 * committed or not. It moves with every change, and not with a commit.
	 */
	public function getFingerprint(string $path): ?string;

	/**
	 * Every file this branch changed against the main line, committed and
	 * uncommitted together. That is what a reviewer has to judge.
	 *
	 * @return array<int, string>
	 */
	public function getChangedFiles(string $path): array;

	/**
	 * The same files as getChangedFiles, each with what kind of change it is
	 * and how many lines came in and went out, measured from where the branch
	 * left the main line.
	 */
	public function getBranchChanges(string $path): ChangeObjectCollection;

	/**
	 * Writes the standing changes down as one commit, or only the files that
	 * were picked. Nothing leaves the machine; pushing is a step of its own.
	 *
	 * @param  array<int, string>|null  $files
	 */
	public function commit(string $path, string $title, string $body, ?array $files = null): bool;

	/**
	 * Sends the commits to the upstream, or creates the branch of the same
	 * name on origin when nothing is followed yet. Never to the main line.
	 */
	public function push(string $path): bool;

	/**
	 * Rebases the branch on the main line of origin. Returns why it did not
	 * work, or null when it did; a conflict leaves everything as it was.
	 */
	public function rebase(string $path): ?string;

	/**
	 * The last rebase DevDock did here; null when there was none.
	 */
	public function getLastRebase(string $path): ?RebaseObject;

	/**
	 * The commits that are on no remote yet, newest first.
	 *
	 * @return array<int, array{sha: string, at: CarbonImmutable, title: string}>
	 */
	public function getUnpushedCommits(string $path): array;

	/**
	 * The branches on origin a task branch may follow; the main line is left
	 * out on purpose.
	 *
	 * @return array<int, string>
	 */
	public function getRemoteBranches(string $path): array;

	/**
	 * Lets the branch follow another branch on origin, or nothing at all.
	 */
	public function setUpstream(string $path, ?string $upstream): bool;

	/**
	 * Throws the standing state of a working copy away, for when something
	 * outside this repository changed what is in it. The next look reads it
	 * from git again.
	 */
	public function forgetStatus(string $path): void;

	/**
	 * Reads the state of every working copy again and keeps it for the given
	 * time, so a page finds it ready instead of waiting on git.
	 */
	public function refreshStatuses(int $keepSeconds): void;
}
