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
	 * Lays the context down in a working copy, where Claude Code reads it by
	 * itself. Returns false when the folder is not there.
	 */
	public function write(string $worktreePath, string $contents): bool;

	/**
	 * Lays down what one lens is asked to judge, as a file it reads itself.
	 */
	public function writeLensContext(string $worktreePath, string $lens, string $contents): bool;

	/**
	 * Lays down what one lens made of it, approved or not.
	 */
	public function writeReport(string $worktreePath, string $lens, string $contents): bool;

	/**
	 * Takes the assignments and the reports of the round before off. Neither
	 * holds once the work has moved on.
	 */
	public function clearRound(string $worktreePath): void;

	/**
	 * Where one lens's assignment lands, relative to the working copy.
	 */
	public function lensPath(string $lens): string;

	/**
	 * Where one lens's report lands, relative to the working copy.
	 */
	public function reportPath(string $lens): string;

	/**
	 * The folder every report of a round stands in, so the agent can be
	 * pointed at it.
	 */
	public function reportFolder(): string;

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
