<?php

declare(strict_types=1);

namespace Src\Review\Repositories;

use Illuminate\Support\Facades\Process;
use Src\Review\Contracts\AgentContextRepositoryContract;
use Src\Review\Enums\WorkflowStepEnum;

class AgentContextRepository implements AgentContextRepositoryContract
{
	/**
	 * Claude Code reads every markdown file under .claude/rules by itself, so
	 * the context arrives without anything being pasted into a prompt.
	 */
	private const RELATIVE_PATH = '.claude/rules/devdock-agent.md';

	/**
	 * What one lens is asked to judge. These stand outside .claude/rules on
	 * purpose: everything in there is read by every session in the folder, and
	 * no lens needs the assignment of the other five.
	 */
	private const LENS_PATH = '.devdock/lens/%s.md';

	/**
	 * What one lens made of it, written whether it approved or not. Together
	 * they are the record of a round: who looked, at what, and what came of it.
	 */
	private const REPORT_PATH = '.devdock/rapport/%s.md';

	private const ROUND_FOLDER = '.devdock';

	/**
	 * Two lines keep everything DevDock lays down out of git, however many
	 * lenses there are.
	 *
	 * @var array<int, string>
	 */
	private const EXCLUDE_PATTERNS = ['.claude/rules/devdock-*.md', '.devdock/'];

	private const STANDING = 'standing';

	public function getBlocks(): array
	{
		$blocks = [self::STANDING => $this->read(self::STANDING)];

		foreach (WorkflowStepEnum::cases() as $step) {
			$blocks[$step->value] = $this->read($step->value);
		}

		return $blocks;
	}

	public function saveBlocks(array $blocks): void
	{
		foreach ($blocks as $key => $text) {
			if ($this->isKnown($key)) {
				file_put_contents($this->fileFor($key), trim($text) . "\n");
			}
		}
	}

	public function write(string $worktreePath, string $contents): bool
	{
		if (! is_dir($worktreePath)) {
			return false;
		}

		$file = $worktreePath . '/' . self::RELATIVE_PATH;
		$directory = dirname($file);

		if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
			return false;
		}

		return file_put_contents($file, $contents) !== false;
	}

	public function writeLensContext(string $worktreePath, string $lens, string $contents): bool
	{
		return $this->lay($worktreePath, $this->lensPath($lens), $contents);
	}

	public function writeReport(string $worktreePath, string $lens, string $contents): bool
	{
		return $this->lay($worktreePath, $this->reportPath($lens), $contents);
	}

	public function clearRound(string $worktreePath): void
	{
		foreach ([self::LENS_PATH, self::REPORT_PATH] as $pattern) {
			foreach (glob($worktreePath . '/' . sprintf($pattern, '*')) ?: [] as $file) {
				@unlink($file);
			}
		}
	}

	public function lensPath(string $lens): string
	{
		return sprintf(self::LENS_PATH, $this->readable($lens));
	}

	public function reportPath(string $lens): string
	{
		return sprintf(self::REPORT_PATH, $this->readable($lens));
	}

	public function reportFolder(): string
	{
		return dirname(sprintf(self::REPORT_PATH, 'x'));
	}

	/**
	 * Lays one file down in a working copy, making the folder it needs.
	 */
	private function lay(string $worktreePath, string $relativePath, string $contents): bool
	{
		if (! is_dir($worktreePath)) {
			return false;
		}

		$file = $worktreePath . '/' . $relativePath;
		$directory = dirname($file);

		if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
			return false;
		}

		return file_put_contents($file, trim($contents) . "\n") !== false;
	}

	/**
	 * A lens name as a file name: letters, digits and dashes.
	 */
	private function readable(string $lens): string
	{
		$name = preg_replace('/[^a-z0-9-]+/', '-', strtolower($lens)) ?? '';

		return trim($name, '-');
	}

	public function exclude(string $worktreePath): void
	{
		$result = Process::path($worktreePath)->run(['git', 'rev-parse', '--git-common-dir']);

		if (! $result->successful()) {
			return;
		}

		$gitDirectory = trim($result->output());

		// git answers with a relative path when the folder is the checkout itself
		if (! str_contains($gitDirectory, ':') && ! str_starts_with($gitDirectory, '/')) {
			$gitDirectory = $worktreePath . '/' . $gitDirectory;
		}

		$exclude = $gitDirectory . '/info/exclude';
		$standing = is_file($exclude) ? (string) file_get_contents($exclude) : '';

		$missing = array_values(array_filter(
			self::EXCLUDE_PATTERNS,
			fn (string $pattern) => ! str_contains($standing, $pattern),
		));

		if ($missing === []) {
			return;
		}

		if (! is_dir(dirname($exclude))) {
			mkdir(dirname($exclude), 0775, true);
		}

		file_put_contents(
			$exclude,
			rtrim($standing)
. "\n\n# DevDock legt hier de context en de rapporten neer; die horen niet in git\n"
. implode("\n", $missing) . "\n",
		);
	}

	public function relativePath(): string
	{
		return self::RELATIVE_PATH;
	}

	private function read(string $key): string
	{
		$file = $this->fileFor($key);

		return is_file($file) ? trim((string) file_get_contents($file)) : $this->defaultFor($key);
	}

	private function fileFor(string $key): string
	{
		$directory = storage_path('app/context');

		if (! is_dir($directory)) {
			mkdir($directory, 0775, true);
		}

		return $directory . '/' . $key . '.md';
	}

	private function isKnown(string $key): bool
	{
		return $key === self::STANDING || WorkflowStepEnum::tryFrom($key) !== null;
	}

	/**
	 * The text an agent reads when nothing has been written over it. {name} is
	 * filled in with the name this agent was given.
	 */
	private function defaultFor(string $key): string
	{
		return match ($key) {
			self::STANDING => implode("\n\n", [
				'You are {name}, the product owner of this task and a senior Laravel developer. You own the outcome: you decide what good looks like here, and you say so when something is wrong — also when that is not what was asked.',
				'Several sessions run at the same time, each on its own task, which is why you do not work in the main checkout but in a working copy of your own. Stay inside it: touch no other folder and switch no branch.',
				'Before you do anything, check that you are on the branch of this task. If you are not, say so and wait.',
				'Branch, pull request and ticket belong together through the ticket number. Do not invent anything else for it and rename nothing.',
				'You do not commit, do not push and post no reply anywhere until David has said yes to it. Put it to him, wait for his go, and only then do it.',
			]),
			WorkflowStepEnum::BUILDING->value => 'You are building. The working copy was set up for this task. Read the code that belongs to it first and put a plan to David before you change anything. Keep the change small and on the subject, and run the tests of what you touch.',
			WorkflowStepEnum::DELIVERED->value => 'The work has been handed over and sits with the lenses. Start nothing new and change nothing further, unless David asks for it.',
			WorkflowStepEnum::REVIEW->value => 'The lenses left findings. Work through them point by point and do not widen the scope. If something is already fixed, say which commit did it. If you disagree with a point, write a proposal and show it to David.',
			WorkflowStepEnum::READY_TO_MERGE->value => 'Every lens agreed. Do not touch the code any more; what follows now is the merge.',
			WorkflowStepEnum::MERGED->value => 'The pull request is merged. The task is done and the working copy may be cleared up. Start no new work here.',
			default => '',
		};
	}
}
