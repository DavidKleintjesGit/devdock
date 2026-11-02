<?php

declare(strict_types=1);

namespace Src\Review\Contracts;

interface AgentContextRepositoryContract
{
	/**
	 * The texts the context is built from: one standing block plus one per
	 * step. Missing blocks come back as the default.
	 *
	 * @return array<string, string>
	 */
	public function getBlocks(): array;

	/**
	 * @param  array<string, string>  $blocks
	 */
	public function saveBlocks(array $blocks): void;

	/**
	 * Lays the context down in a working copy, where each turn of the agent
	 * picks it up. Returns false when the folder is not there.
	 */
	public function write(string $worktreePath, string $contents): bool;

	/**
	 * The task context as it stands in a working copy; null before one was written.
	 */
	public function getAgentContext(string $worktreePath): ?string;

	/**
	 * Lays down what one lens is asked to judge, as a file it reads itself.
	 */
	public function writeLensContext(string $worktreePath, string $lens, string $contents): bool;

	/**
	 * Lays down what one lens made of a round, approved or not, in a folder of
	 * the task and the round, so earlier rounds stay readable.
	 */
	public function writeReport(string $worktreePath, string $card, int $round, string $lens, string $contents): bool;

	/**
	 * Takes the assignments of the round before off; they no longer hold once
	 * the work has moved on. The reports stay, a folder per round.
	 */
	public function clearRound(string $worktreePath): void;

	/**
	 * Takes every report of this task out of the working copy, for when the
	 * task is cleaned up.
	 */
	public function removeReports(string $worktreePath, string $card): bool;

	public function hasReports(string $worktreePath, string $card): bool;

	/**
	 * Where one lens's assignment lands, relative to the working copy.
	 */
	public function lensPath(string $lens): string;

	/**
	 * Where one lens's report of a round lands, relative to the working copy.
	 */
	public function reportPath(string $card, int $round, string $lens): string;

	/**
	 * The folder every report of a round stands in, so the agent can be
	 * pointed at it.
	 */
	public function reportFolder(string $card, int $round): string;

	/**
	 * Keeps the context out of git without touching .gitignore, which would
	 * be committed. The local exclude file never leaves the machine.
	 */
	public function exclude(string $worktreePath): void;

	/**
	 * Where the context lands, relative to the working copy.
	 */
	public function relativePath(): string;
}
