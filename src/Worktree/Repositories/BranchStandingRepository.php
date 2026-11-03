<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Worktree\Contracts\BranchStandingRepositoryContract;
use Src\Worktree\Objects\BranchStandingObject;

class BranchStandingRepository implements BranchStandingRepositoryContract
{
	/**
	 * The branches asked for, by repository, so a refresh knows what to read
	 * again before anyone asks.
	 */
	private const KNOWN_KEY = 'branch.standing.known';

	private const FETCH_SECONDS = 300;

	/**
	 * A merge-tree on a large repository takes a second; more of them at once
	 * only slow each other down.
	 */
	private const AT_ONCE = 6;

	public function getFor(string $repositoryPath, string $branch): BranchStandingObject
	{
		$this->warm($repositoryPath, [$branch]);

		return BranchStandingObject::from((array) Cache::get($this->key($repositoryPath, $branch), []));
	}

	public function getKnown(string $repositoryPath, string $branch): ?BranchStandingObject
	{
		$this->remember($repositoryPath, [$branch]);

		$standing = Cache::get($this->key($repositoryPath, $branch));

		return is_array($standing) ? BranchStandingObject::from($standing) : null;
	}

	public function warm(string $repositoryPath, array $branches, ?int $keepSeconds = null, bool $again = false): void
	{
		$this->remember($repositoryPath, $branches);

		$cold = array_values(array_unique(array_filter(
			$branches,
			fn (string $branch) => $again || ! Cache::has($this->key($repositoryPath, $branch)),
		)));

		if ($cold === [] || ! is_dir($repositoryPath)) {
			return;
		}

		$mainBranch = $this->mainBranch($repositoryPath);
		$shas = $this->shasIn($repositoryPath);
		$mainSha = $shas['refs/remotes/origin/' . $mainBranch] ?? null;
		$seconds = $keepSeconds ?? (int) config('devdock.worktree.status_seconds');

		// the local branch holds what a working copy committed; origin is the fallback
		$branchShas = [];

		foreach ($cold as $branch) {
			$branchShas[$branch] = $shas['refs/heads/' . $branch] ?? $shas['refs/remotes/origin/' . $branch] ?? null;
		}

		// the same two commits always give the same answer, so only a moved branch or main line is read again
		$unread = $mainSha === null ? [] : array_values(array_unique(array_filter(
			$branchShas,
			fn (?string $sha) => $sha !== null && ! Cache::has($this->resultKey($mainSha, $sha)),
		)));

		foreach (array_chunk($unread, self::AT_ONCE) as $chunk) {
			$this->read($repositoryPath, $mainSha, $chunk);
		}

		foreach ($cold as $branch) {
			$result = $mainSha === null || $branchShas[$branch] === null
				? ['behind' => 0, 'conflicted_files' => []]
				: (array) Cache::get($this->resultKey($mainSha, $branchShas[$branch]), ['behind' => 0, 'conflicted_files' => []]);

			Cache::put($this->key($repositoryPath, $branch), $result + ['main_branch' => $mainBranch], $seconds);
		}
	}

	/**
	 * Reads a handful of branches against the main line side by side and keeps
	 * the answer under the two commits it is about.
	 *
	 * @param  array<int, string>  $branchShas
	 */
	private function read(string $repositoryPath, string $mainSha, array $branchShas): void
	{
		$results = Process::pool(function (Pool $pool) use ($repositoryPath, $mainSha, $branchShas): void {
			foreach ($branchShas as $index => $branchSha) {
				$pool->as('behind-' . $index)->path($repositoryPath)->command(['git', 'rev-list', '--count', $branchSha . '..' . $mainSha]);
				// merges in memory only: nothing in the repository or a working copy changes
				$pool->as('merge-' . $index)->path($repositoryPath)->timeout(60)
					->command(['git', 'merge-tree', '--write-tree', '--name-only', '--no-messages', $mainSha, $branchSha]);
			}
		})->start()->wait();

		foreach ($branchShas as $index => $branchSha) {
			$behind = $results['behind-' . $index];
			$merged = $results['merge-' . $index];

			Cache::put($this->resultKey($mainSha, $branchSha), [
				'behind' => $behind->successful() ? (int) trim($behind->output()) : 0,
				// exit code 1 is a conflict: the first line is the tree, the rest the clashing files
				'conflicted_files' => $merged->exitCode() === 1
					? array_values(array_filter(array_slice(preg_split('/\R/', trim($merged->output())) ?: [], 1)))
					: [],
			], now()->addWeek());
		}
	}

	public function refresh(int $keepSeconds): void
	{
		foreach ((array) Cache::get(self::KNOWN_KEY, []) as $repositoryPath => $branches) {
			$this->fetchMain((string) $repositoryPath);
			$this->warm((string) $repositoryPath, array_keys((array) $branches), $keepSeconds, again: true);
		}
	}

	public function forget(string $repositoryPath, string $branch): void
	{
		Cache::forget($this->key($repositoryPath, $branch));
	}

	/**
	 * Brings the main line of origin up to date, at most every few minutes:
	 * how far behind a branch is means nothing against an old copy of it.
	 */
	private function fetchMain(string $repositoryPath): void
	{
		if (! is_dir($repositoryPath) || ! Cache::add('branch.standing.fetched.' . md5($repositoryPath), true, self::FETCH_SECONDS)) {
			return;
		}

		Process::path($repositoryPath)->timeout(120)->run(['git', 'fetch', '--quiet', 'origin', $this->mainBranch($repositoryPath)]);
	}

	/**
	 * @param  array<int, string>  $branches
	 */
	private function remember(string $repositoryPath, array $branches): void
	{
		$known = (array) Cache::get(self::KNOWN_KEY, []);
		$before = $known[$repositoryPath] ?? [];
		$known[$repositoryPath] = $before + array_fill_keys($branches, true);

		if ($known[$repositoryPath] !== $before) {
			Cache::forever(self::KNOWN_KEY, $known);
		}
	}

	/**
	 * The commit every local and origin branch points at, by its full ref.
	 *
	 * @return array<string, string>
	 */
	private function shasIn(string $repositoryPath): array
	{
		$result = Process::path($repositoryPath)->run(['git', 'for-each-ref', '--format=%(refname) %(objectname)', 'refs/heads', 'refs/remotes/origin']);

		if (! $result->successful()) {
			return [];
		}

		$shas = [];

		foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
			[$ref, $sha] = array_pad(explode(' ', trim($line), 2), 2, null);

			if ($ref !== '' && $sha !== null) {
				$shas[$ref] = $sha;
			}
		}

		return $shas;
	}

	private function resultKey(string $mainSha, string $branchSha): string
	{
		return 'branch.standing.result.' . $mainSha . '.' . $branchSha;
	}

	/**
	 * Shares its cache with WorktreeRepository, so the main line is asked once.
	 */
	private function mainBranch(string $repositoryPath): string
	{
		return Cache::remember('worktree.main.' . md5($repositoryPath), 86400, function () use ($repositoryPath): string {
			$result = Process::path($repositoryPath)
				->run(['git', 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

			$name = trim($result->output());

			return $result->successful() && $name !== ''
				? str_replace('origin/', '', $name)
				: 'master';
		});
	}

	private function key(string $repositoryPath, string $branch): string
	{
		return 'branch.standing.' . md5($repositoryPath . '|' . $branch);
	}
}
