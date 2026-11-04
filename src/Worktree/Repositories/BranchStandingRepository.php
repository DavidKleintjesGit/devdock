<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Packages\Process\LocalCommand;
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

	/**
	 * The key under a branch in git's config that names the branch its pull
	 * request merges into; WorktreeRepository writes it.
	 */
	private const TARGET_KEY = 'devdock-target';

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

		$targets = $this->targetsOf($repositoryPath, $cold);
		$shas = $this->shasIn($repositoryPath);
		$seconds = $keepSeconds ?? (int) config('devdock.worktree.status_seconds');

		// the local branch holds what a working copy committed; origin is the fallback
		$branchShas = [];
		$targetShas = [];

		foreach ($cold as $branch) {
			$branchShas[$branch] = $shas['refs/heads/' . $branch] ?? $shas['refs/remotes/origin/' . $branch] ?? null;
			$targetShas[$branch] = $shas['refs/remotes/origin/' . $targets[$branch]] ?? null;
		}

		// the same two commits always give the same answer, so only a moved branch or target is read again
		$unread = [];

		foreach ($cold as $branch) {
			if ($branchShas[$branch] !== null && $targetShas[$branch] !== null && ! Cache::has($this->resultKey($targetShas[$branch], $branchShas[$branch]))) {
				$unread[$targetShas[$branch] . '|' . $branchShas[$branch]] = [
					'target' => $targetShas[$branch],
					'branch' => $branchShas[$branch],
					'forkPoint' => $this->ownBase($repositoryPath, $branch, $targets[$branch], $branchShas[$branch]),
				];
			}
		}

		foreach (array_chunk(array_values($unread), self::AT_ONCE) as $chunk) {
			$this->read($repositoryPath, $chunk);
		}

		foreach ($cold as $branch) {
			$result = $targetShas[$branch] === null || $branchShas[$branch] === null
				? ['behind' => 0, 'conflicted_files' => []]
				: (array) Cache::get($this->resultKey($targetShas[$branch], $branchShas[$branch]), ['behind' => 0, 'conflicted_files' => []]);

			Cache::put($this->key($repositoryPath, $branch), $result + ['target_branch' => $targets[$branch]], $seconds);
		}
	}

	/**
	 * Reads a handful of branches against the branch each merges into, side by
	 * side, and keeps the answer under the two commits it is about.
	 *
	 * @param  list<array{target: string, branch: string, forkPoint: ?string}>  $pairs
	 */
	private function read(string $repositoryPath, array $pairs): void
	{
		$results = Process::pool(function (Pool $pool) use ($repositoryPath, $pairs): void {
			foreach ($pairs as $index => $pair) {
				$pool->as('behind-' . $index)->path($repositoryPath)->command(['git', 'rev-list', '--count', $pair['branch'] . '..' . $pair['target']]);
				// merges in memory only: nothing in the repository or a working copy changes. From the fork point it
				// replays only the own commits, the way the rebase does, so old copies of a rewritten target do not clash
				$pool->as('merge-' . $index)->path($repositoryPath)->timeout(60)
					->command(['git', 'merge-tree', '--write-tree', '--name-only', '--no-messages', ...($pair['forkPoint'] === null ? [] : ['--merge-base=' . $pair['forkPoint']]), $pair['target'], $pair['branch']]);
			}
		})->start()->wait();

		foreach ($pairs as $index => $pair) {
			$behind = $results['behind-' . $index];
			$merged = $results['merge-' . $index];

			Cache::put($this->resultKey($pair['target'], $pair['branch']), [
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
			$this->fetchTargets((string) $repositoryPath, array_keys((array) $branches));
			$this->warm((string) $repositoryPath, array_keys((array) $branches), $keepSeconds, again: true);
		}
	}

	public function forget(string $repositoryPath, string $branch): void
	{
		Cache::forget($this->key($repositoryPath, $branch));
	}

	/**
	 * Brings the branches the given ones merge into up to date on origin, at
	 * most every few minutes: how far behind a branch is means nothing against
	 * an old copy of its target.
	 *
	 * @param  array<int, string>  $branches
	 */
	private function fetchTargets(string $repositoryPath, array $branches): void
	{
		if (! is_dir($repositoryPath) || ! Cache::add('branch.standing.fetched.' . md5($repositoryPath), true, self::FETCH_SECONDS)) {
			return;
		}

		$targets = array_values(array_unique([$this->mainBranch($repositoryPath), ...array_values($this->targetsOf($repositoryPath, $branches))]));

		Process::path($repositoryPath)->timeout(120)->run(['git', 'fetch', '--quiet', 'origin', ...$targets]);
	}

	/**
	 * Where the own work of a branch begins: the stand of its target that
	 * WorktreeRepository remembered while the branch held it, else the old tip of
	 * the target as git's reflog knows it, or null to let git take the common
	 * ancestor.
	 */
	private function ownBase(string $repositoryPath, string $branch, string $target, string $branchSha): ?string
	{
		$remembered = trim(LocalCommand::run($repositoryPath, ['git', 'config', '--get', 'branch.' . $branch . '.devdock-base'])->output());

		if ($remembered !== '' && LocalCommand::run($repositoryPath, ['git', 'merge-base', '--is-ancestor', $remembered, $branchSha])->successful()) {
			return $remembered;
		}

		$result = LocalCommand::run($repositoryPath, ['git', 'merge-base', '--fork-point', 'refs/remotes/origin/' . $target, $branchSha]);

		return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : null;
	}

	/**
	 * The branch each branch merges into: the target its pull request names,
	 * kept in git's config under the branch, or the main line.
	 *
	 * @param  array<int, string>  $branches
	 * @return array<string, string>
	 */
	private function targetsOf(string $repositoryPath, array $branches): array
	{
		$result = LocalCommand::run($repositoryPath, ['git', 'config', '--get-regexp', '^branch\..*\.' . self::TARGET_KEY . '$']);
		$marked = [];

		foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
			// a line reads "branch.<name>.devdock-target <target>"; the name itself may hold dots
			if (preg_match('/^branch\.(.+)\.' . preg_quote(self::TARGET_KEY, '/') . '\s+(\S+)$/', trim($line), $parts) === 1) {
				$marked[$parts[1]] = $parts[2];
			}
		}

		$mainBranch = $this->mainBranch($repositoryPath);

		return collect($branches)
			->mapWithKeys(fn (string $branch): array => [$branch => $marked[$branch] ?? $mainBranch])
			->all();
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
		$result = LocalCommand::run($repositoryPath, ['git', 'for-each-ref', '--format=%(refname) %(objectname)', 'refs/heads', 'refs/remotes/origin']);

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

	private function resultKey(string $targetSha, string $branchSha): string
	{
		// names how the answer was read, so an answer read another way is never taken for it
		return 'branch.standing.result.fork.' . $targetSha . '.' . $branchSha;
	}

	/**
	 * Shares its cache with WorktreeRepository, so the main line is asked once.
	 */
	private function mainBranch(string $repositoryPath): string
	{
		return Cache::remember('worktree.main.' . md5($repositoryPath), 86400, function () use ($repositoryPath): string {
			$result = LocalCommand::run($repositoryPath, ['git', 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

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
