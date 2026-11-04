<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Packages\Process\LocalCommand;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\DiffViewEnum;
use Src\Worktree\Enums\RebaseOutcomeEnum;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Models\Worktree as WorktreeEloquent;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\ChangeObjectCollection;
use Src\Worktree\Objects\RebaseObject;
use Src\Worktree\Objects\RebaseOutcomeObject;
use Src\Worktree\Objects\WorktreeEntity;
use Src\Worktree\Objects\WorktreeEntityCollection;
use Src\Worktree\Objects\WorktreeId;
use Src\Worktree\Objects\WorktreeObject;

class WorktreeRepository implements WorktreeRepositoryContract
{
	/**
	 * Laying down a working copy of a large repository takes longer than a
	 * web request is willing to wait for.
	 */
	private const TIMEOUT = 300;

	/**
	 * The file that tells WakaTime which project this folder belongs to.
	 */
	private const PROJECT_NAME_FILE = '.wakatime-project';

	/**
	 * Where DevDock keeps what it did in a working copy, out of git.
	 */
	private const DEVDOCK_FOLDER = '.devdock';

	private const REBASE_FILE = '.devdock/rebase.json';

	/**
	 * The key under a branch in git's config that names the branch its pull request merges into.
	 */
	private const TARGET_KEY = 'devdock-target';

	/**
	 * How long what the fetch round read ahead stays. A copy that moves is
	 * read again in the next round, so this only bounds a copy left alone.
	 */
	private const READ_AHEAD_SECONDS = 3600;

	/**
	 * Set while the fetch round reads ahead: every reader then reads git again
	 * and keeps the answer this long, instead of handing back what it kept.
	 */
	private ?int $readAheadSeconds = null;

	public function create(WorktreeObject $worktreeObject): WorktreeEntity
	{
		// a folder that already stands there was not made here, and is not ours to remove
		$isMadeHere = ! is_dir($worktreeObject->path);
		$branchCreated = $isMadeHere && $this->addToGit($worktreeObject);

		$this->pinProjectName($worktreeObject->path, $worktreeObject->project);

		$worktreeEloquent = WorktreeEloquent::query()->updateOrCreate(
			[
				'project' => $worktreeObject->project,
				'branch' => $worktreeObject->branch,
			],
			[
				'path' => $worktreeObject->path,
				'site' => $worktreeObject->site,
				'card' => $worktreeObject->card,
				'origin' => $isMadeHere ? WorktreeOriginEnum::MADE : WorktreeOriginEnum::ADOPTED,
				'branch_created' => $branchCreated,
			],
		);

		return $this->mapToEntity($worktreeEloquent);
	}

	public function getPlaces(string $repositoryPath): array
	{
		$result = LocalCommand::run($repositoryPath, ['git', 'worktree', 'list', '--porcelain']);

		if (! $result->successful()) {
			return [];
		}

		$places = [];
		$path = null;

		foreach (explode("\n", $result->output()) as $line) {
			$line = trim($line);

			if (str_starts_with($line, 'worktree ')) {
				$path = substr($line, 9);
			}

			if (str_starts_with($line, 'branch refs/heads/') && $path !== null) {
				$places[substr($line, 18)] = $path;
			}
		}

		return $places;
	}

	public function pinSite(WorktreeId $worktreeId, ?string $site): WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()->findOrFail($worktreeId->id);

		$worktreeEloquent->update(['site' => $site]);

		return $this->mapToEntity($worktreeEloquent->fresh());
	}

	public function markDatabase(WorktreeId $worktreeId, ?string $database): WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()->findOrFail($worktreeId->id);

		$worktreeEloquent->update(['database' => $database]);

		return $this->mapToEntity($worktreeEloquent->fresh());
	}

	public function getAll(): WorktreeEntityCollection
	{
		$worktreeEloquents = WorktreeEloquent::query()
			->orderBy('project')
			->orderBy('branch')
			->get();

		$this->warmStatuses($worktreeEloquents->pluck('path')->map(fn (mixed $path) => (string) $path)->all());

		$worktreeEntities = $worktreeEloquents
			->map(fn (WorktreeEloquent $worktreeEloquent) => $this->mapToEntity($worktreeEloquent))
			->all();

		return WorktreeEntityCollection::fromArray($worktreeEntities);
	}

	public function getFor(string $project, string $branch): ?WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()
			->where('project', $project)
			->where('branch', $branch)
			->first();

		return $worktreeEloquent === null ? null : $this->mapToEntity($worktreeEloquent);
	}

	public function rename(WorktreeId $worktreeId, string $branch): WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()->findOrFail($worktreeId->id);

		$from = (string) $worktreeEloquent->path;
		$to = dirname($from) . '/' . str_replace(['/', '\\'], '-', $branch);
		$repository = $this->repositoryPathOf($from);

		$renamed = Process::path($repository)
			->timeout(self::TIMEOUT)
			->run(['git', 'branch', '-m', (string) $worktreeEloquent->branch, $branch]);

		if (! $renamed->successful()) {
			throw new WorktreeNotPossibleException(trim($renamed->errorOutput() ?: $renamed->output()));
		}

		// git keeps its own record of where a working copy sits, so the folder
		// moves through git and not with a plain rename
		if ($from !== $to) {
			$moved = Process::path($repository)
				->timeout(self::TIMEOUT)
				->run(['git', 'worktree', 'move', $from, $to]);

			if (! $moved->successful()) {
				throw new WorktreeNotPossibleException(trim($moved->errorOutput() ?: $moved->output()));
			}
		}

		$worktreeEloquent->update(['branch' => $branch, 'path' => $to]);

		return $this->mapToEntity($worktreeEloquent->fresh());
	}

	public function remove(WorktreeId $worktreeId): bool
	{
		$worktreeEloquent = WorktreeEloquent::query()->find($worktreeId->id);

		if ($worktreeEloquent === null) {
			return false;
		}

		$path = (string) $worktreeEloquent->path;
		$origin = $worktreeEloquent->origin ?? WorktreeOriginEnum::MADE;

		// a working copy stands two folders deep in its checkout; once the folder is gone git cannot say so itself
		$repositoryPath = is_dir($path) ? $this->repositoryPathOf($path) : dirname($path, 2);

		// a folder DevDock did not make stays where it is; only the record goes
		if ($origin->isOurs() && is_dir($path)) {
			// the pinned project name is ours; without taking it back git sees an
			// unknown file and refuses, while real work should still stop us
			@unlink($path . '/' . self::PROJECT_NAME_FILE);

			$removed = Process::path($repositoryPath)
				->timeout(self::TIMEOUT)
				->run(['git', 'worktree', 'remove', $path])
				->successful();

			if (! $removed) {
				$this->pinProjectName($path, (string) $worktreeEloquent->project);

				return false;
			}
		}

		if ($origin->isOurs() && $worktreeEloquent->branch_created) {
			$this->deleteMergedBranch($repositoryPath, (string) $worktreeEloquent->branch);
		}

		$worktreeEloquent->delete();

		return true;
	}

	public function isMerged(string $repositoryPath, string $branch): bool
	{
		return LocalCommand::run($repositoryPath, ['git', 'merge-base', '--is-ancestor', 'refs/heads/' . $branch, 'origin/' . $this->targetOf($repositoryPath, $branch)])
			->successful();
	}

	/**
	 * Takes away a branch DevDock created, but only when the main line on
	 * origin already holds every commit of it; otherwise it stays.
	 */
	private function deleteMergedBranch(string $repositoryPath, string $branch): void
	{
		if ($this->isMerged($repositoryPath, $branch)) {
			Process::path($repositoryPath)->run(['git', 'branch', '-D', $branch]);
		}
	}

	public function getBranchChanges(string $path): ChangeObjectCollection
	{
		if (! is_dir($path)) {
			return ChangeObjectCollection::fromArray([]);
		}

		$rows = $this->remember(
			$this->branchChangesKey($path),
			fn () => $this->readChangesSince($path, $this->branchBase($path)),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	/**
	 * Every file changed since the given commit, including what is not
	 * committed yet and files git never saw.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function readChangesSince(string $path, ?string $base, ?string $until = null): array
	{
		if ($base === null) {
			return [];
		}

		// without an end the working files themselves are the end
		$range = $until === null ? [$base] : [$base, $until];
		$kinds = [];

		foreach ($this->lines($path, ['git', 'diff', '--name-status', '--find-renames', ...$range]) as $line) {
			$parts = explode("\t", $line);
			$kinds[end($parts)] = $parts[0];
		}

		$rows = [];

		foreach ($this->lines($path, ['git', 'diff', '--numstat', '--find-renames', ...$range]) as $line) {
			$parts = explode("\t", $line);

			if (count($parts) < 3) {
				continue;
			}

			// a rename reads 'old => new' or 'folder/{old => new}'; the page shows where it lives now
			$file = (string) preg_replace('/\{[^{}]* => ([^{}]*)\}/', '$1', $parts[2]);
			$file = str_contains($file, ' => ') ? trim(explode(' => ', $file)[1]) : $file;

			$rows[$file] = [
				'path' => str_replace('//', '/', $file),
				'status' => $kinds[$file] ?? 'M',
				'added' => (int) $parts[0],
				'removed' => (int) $parts[1],
			];
		}

		if ($until !== null) {
			return array_values($rows);
		}

		// git diff leaves out what it has never seen; those files are new in full
		foreach ($this->lines($path, ['git', 'ls-files', '--others', '--exclude-standard']) as $untracked) {
			if ($untracked === self::PROJECT_NAME_FILE || isset($rows[$untracked])) {
				continue;
			}

			$contents = @file_get_contents($path . '/' . $untracked);

			$rows[$untracked] = [
				'path' => $untracked,
				'status' => '??',
				'added' => $contents === false ? 0 : substr_count($contents, "\n") + (str_ends_with($contents, "\n") || $contents === '' ? 0 : 1),
				'removed' => 0,
			];
		}

		return array_values($rows);
	}

	/**
	 * The commit where this branch left the branch it merges into on origin.
	 */
	private function branchBase(string $path): ?string
	{
		return $this->forkPoint($path, $this->targetBranch($path));
	}

	/**
	 * Where HEAD left a branch on origin. When that branch was rewritten after
	 * this one was cut from it, as a pull request lower in a stack is after a
	 * rebase, git's reflog still knows the old tip, so only the commits of
	 * this branch count as its own. Without that memory it is the common
	 * ancestor.
	 */
	private function forkPoint(string $path, string $target): ?string
	{
		$forkPoint = LocalCommand::run($path, ['git', 'merge-base', '--fork-point', 'origin/' . $target, 'HEAD']);

		if ($forkPoint->successful() && trim($forkPoint->output()) !== '') {
			return trim($forkPoint->output());
		}

		$mergeBase = LocalCommand::run($path, ['git', 'merge-base', 'origin/' . $target, 'HEAD']);

		return $mergeBase->successful() ? (trim($mergeBase->output()) ?: null) : null;
	}

	/**
	 * The rebase that moves only the own commits of this branch onto its target.
	 *
	 * @return list<string>
	 */
	private function rebaseCommand(string $path, string $target, bool $setsAsideWork): array
	{
		$forkPoint = $this->forkPoint($path, $target);

		return [
			'git',
			'rebase',
			...($setsAsideWork ? ['--autostash'] : []),
			...($forkPoint === null ? [] : ['--onto', 'origin/' . $target, $forkPoint]),
			...($forkPoint === null ? ['origin/' . $target] : []),
		];
	}

	public function getChanges(string $path): ChangeObjectCollection
	{
		if (! is_dir($path)) {
			return ChangeObjectCollection::fromArray([]);
		}

		// alleen de gegevens gaan de cache in: objecten overleven het opslaan
		// niet en komen er half terug
		$rows = $this->remember(
			$this->changesKey($path),
			// by content, so a file that only changed its line endings does not count
			fn () => $this->readChangesSince($path, 'HEAD'),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	public function getChangesFor(string $path, DiffViewEnum $view): ChangeObjectCollection
	{
		// a round reads between two snapshots, which only getChangesBetween knows
		if ($view === DiffViewEnum::ROUND) {
			return ChangeObjectCollection::fromArray([]);
		}

		if ($view !== DiffViewEnum::UNPUSHED) {
			return $view === DiffViewEnum::UNCOMMITTED ? $this->getChanges($path) : $this->getBranchChanges($path);
		}

		if (! is_dir($path)) {
			return ChangeObjectCollection::fromArray([]);
		}

		$rows = $this->remember(
			'worktree.unpushed-changes.' . md5($path),
			fn () => $this->readChangesSince($path, $this->pushedBase($path)),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	public function getDiff(string $path, string $file, DiffViewEnum $view = DiffViewEnum::BRANCH, int $context = 3): ?string
	{
		if (! is_dir($path) || $file === '') {
			return null;
		}

		$result = LocalCommand::run($path, ['git', 'diff', '--no-color', '--unified=' . $context, $this->diffBase($path, $view), '--', $file]);

		$patch = trim($result->output());

		if ($patch !== '') {
			return $patch;
		}

		// a file git never saw is new in full; one it knows without a patch only changed its line endings
		return $this->lines($path, ['git', 'ls-files', '--others', '--exclude-standard', '--', $file]) === [] ? null : $this->newFile($path, $file);
	}

	public function snapshot(string $path): ?string
	{
		$gitDirectory = $this->gitDirectoryOf($path);

		if ($gitDirectory === null) {
			return null;
		}

		// a copy of the index of the working copy keeps what git knows of each file, so only changed files are read; the real index is never touched
		$index = sys_get_temp_dir() . '/devdock-index-' . bin2hex(random_bytes(8));

		if (is_file($gitDirectory . '/index')) {
			copy($gitDirectory . '/index', $index);
		}

		$environment = ['GIT_INDEX_FILE' => $index];
		$added = Process::path($path)->env($environment)->timeout(self::TIMEOUT)->run(['git', 'add', '--all']);
		$tree = $added->successful() ? Process::path($path)->env($environment)->run(['git', 'write-tree']) : null;

		@unlink($index);

		return $tree !== null && $tree->successful() && trim($tree->output()) !== '' ? trim($tree->output()) : null;
	}

	public function getChangesBetween(string $path, string $from, string $until): ChangeObjectCollection
	{
		if (! is_dir($path) || ! $this->hasObject($path, $from) || ! $this->hasObject($path, $until)) {
			return ChangeObjectCollection::fromArray([]);
		}

		// two snapshots never change, so their difference is read once
		$rows = Cache::rememberForever(
			'worktree.changes-between.' . md5($path . '|' . $from . '|' . $until),
			fn (): array => $this->readChangesSince($path, $from, $until),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	public function getDiffBetween(string $path, string $file, string $from, string $until, int $context = 3): ?string
	{
		if (! is_dir($path) || $file === '') {
			return null;
		}

		$patch = trim(LocalCommand::run($path, ['git', 'diff', '--no-color', '--unified=' . $context, $from, $until, '--', $file])->output());

		return $patch === '' ? null : $patch;
	}

	/**
	 * Whether git still holds an object; a snapshot nothing points at is cleared away by git after a few weeks.
	 */
	private function hasObject(string $path, string $sha): bool
	{
		return LocalCommand::run($path, ['git', 'cat-file', '-e', $sha])->successful();
	}

	public function getGeneratedFiles(string $path, array $files): array
	{
		if (! is_dir($path) || $files === []) {
			return [];
		}

		$patterns = (array) config('devdock.worktree.generated');
		$marked = [];

		// one call for every file: "path: linguist-generated: set"
		$result = LocalCommand::run($path, ['git', 'check-attr', '--stdin', 'linguist-generated'], implode("\n", $files) . "\n");

		foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
			if (preg_match('/^(.+): linguist-generated: (set|true)$/', $line, $found) === 1) {
				$marked[] = $found[1];
			}
		}

		return array_values(array_filter($files, fn (string $file): bool => in_array($file, $marked, true)
			|| collect($patterns)->contains(fn (string $pattern): bool => fnmatch($pattern, $file))));
	}

	/**
	 * What a view sets the working files against.
	 */
	private function diffBase(string $path, DiffViewEnum $view): string
	{
		// the code reader reads every file on its own; working out the base takes up to five git calls
		return $this->remember(
			$this->diffBaseKey($path, $view),
			fn (): string => match ($view) {
				// what changed since the last commit
				DiffViewEnum::UNCOMMITTED => 'HEAD',
				// everything origin does not have yet, committed or not
				DiffViewEnum::UNPUSHED => $this->pushedBase($path) ?? 'HEAD',
				// against the branch it merges into: what this branch did to the file, committed or not
				DiffViewEnum::BRANCH => $this->branchBase($path) ?? 'origin/' . $this->targetBranch($path),
				// read between snapshots elsewhere; against the last commit it shows nothing wrong
				DiffViewEnum::ROUND => 'HEAD',
			},
		);
	}

	private function diffBaseKey(string $path, DiffViewEnum $view): string
	{
		return 'worktree.diff-base.' . $view->value . '.' . md5($path);
	}

	public function getNewCommitCount(string $path): int
	{
		return count($this->newCommits($path));
	}

	/**
	 * The commits whose content origin does not hold yet, oldest first. A commit
	 * a rebase gave a new sha but the same change counts as pushed; so does
	 * everything that came from the main line.
	 *
	 * @return array<int, string>
	 */
	private function newCommits(string $path): array
	{
		$upstream = $this->pushedRef($path);

		if ($upstream === null) {
			return $this->lines($path, ['git', 'rev-list', '--reverse', '--no-merges', 'HEAD', '--not', '--remotes']);
		}

		return $this->lines($path, [
			'git', 'rev-list', '--reverse', '--no-merges', '--right-only', '--cherry-pick',
			$upstream . '...HEAD', '^origin/' . $this->targetBranch($path),
		]);
	}

	/**
	 * The commit to set the working files against to see what a push would
	 * change: the branch as origin holds it, laid on the same main line as
	 * HEAD, so neither a rebase nor the main line moving on shows up. With no
	 * new commits that is HEAD itself, and only what is not committed remains.
	 */
	private function pushedBase(string $path): ?string
	{
		$newCommits = $this->newCommits($path);
		$upstream = $this->pushedRef($path);
		$fork = $this->branchBase($path);

		if ($upstream === null) {
			return $fork;
		}

		if ($newCommits === []) {
			return 'HEAD';
		}

		$merged = $fork === null ? null : LocalCommand::run($path, ['git', 'merge-tree', '--write-tree', '--no-messages', $fork, $upstream]);

		// exit code 1 is a clash: the tree still holds everything that merged, and the clashing files differ for real
		if ($merged !== null && in_array($merged->exitCode(), [0, 1], true)) {
			return $this->commitOfTree($path, (string) strtok(trim($merged->output()), "\n"), (string) $fork);
		}

		// no tree at all; fall back to the commit before the oldest new one
		$parent = LocalCommand::run($path, ['git', 'rev-parse', '--verify', '--quiet', $newCommits[0] . '^']);

		return $parent->successful() && trim($parent->output()) !== '' ? trim($parent->output()) : null;
	}

	/**
	 * The branch on origin: its upstream, or the branch of the same name; null
	 * when it was never pushed.
	 */
	private function pushedRef(string $path): ?string
	{
		foreach (['@{upstream}', 'refs/remotes/origin/' . $this->getBranch($path)] as $ref) {
			$result = LocalCommand::run($path, ['git', 'rev-parse', '--verify', '--quiet', $ref]);

			if ($result->successful() && trim($result->output()) !== '') {
				return trim($result->output());
			}
		}

		return null;
	}

	/**
	 * A commit holding the tree, on top of the given parent: git sets the working
	 * files against a commit, not against a bare tree. Kept per tree, so asking
	 * again does not make another.
	 */
	private function commitOfTree(string $path, string $tree, string $parent): ?string
	{
		if ($tree === '' || $parent === '') {
			return null;
		}

		return Cache::rememberForever('worktree.laid-commit.' . $tree . '.' . $parent, function () use ($path, $tree, $parent): ?string {
			// the commit hangs on no branch; git clears such loose objects away by itself
			$commit = LocalCommand::run($path, ['git', 'commit-tree', $tree, '-p', $parent, '-m', 'devdock: origin laid on the main line']);

			return $commit->successful() ? (trim($commit->output()) ?: null) : null;
		});
	}

	/**
	 * Een bestand dat git nog nooit zag, als toevoeging geschreven, zodat het
	 * er hetzelfde uitziet als de rest.
	 */
	private function newFile(string $path, string $file): ?string
	{
		$whole = $path . '/' . $file;

		if (! is_file($whole)) {
			return null;
		}

		$contents = (string) file_get_contents($whole);
		$lines = $contents === '' ? [] : (preg_split('/\R/', rtrim($contents, "\r\n")) ?: []);

		// the hunk header is what the diff reader goes by; without it the file looks empty
		return implode("\n", [
			'--- /dev/null',
			'+++ b/' . $file,
			'@@ -0,0 +1,' . count($lines) . ' @@',
			...array_map(fn (string $line) => '+' . $line, $lines),
		]);
	}

	public function getFingerprint(string $path): ?string
	{
		if (! is_dir($path)) {
			return null;
		}

		return $this->remember(
			'worktree.fingerprint.' . md5($path),
			function () use ($path) {
				// against the branch it merges into, so a commit of the judged work does not
				// count as a change; files git never saw are taken in by content
				$diff = LocalCommand::run($path, ['git', 'diff', 'origin/' . $this->targetBranch($path)]);
				$untracked = LocalCommand::run($path, ['git', 'ls-files', '--others', '--exclude-standard']);

				$parts = [$diff->output()];

				foreach (preg_split('/\R/', trim($untracked->output())) ?: [] as $file) {
					if ($file !== '' && $file !== self::PROJECT_NAME_FILE && is_file($path . '/' . $file)) {
						$parts[] = $file . ':' . sha1_file($path . '/' . $file);
					}
				}

				return sha1(implode('|', $parts));
			},
		);
	}

	public function getHead(string $path): ?string
	{
		if (! is_dir($path)) {
			return null;
		}

		$result = LocalCommand::run($path, ['git', 'rev-parse', '--short', 'HEAD']);

		return $result->successful() ? trim($result->output()) : null;
	}

	public function getChangedFiles(string $path): array
	{
		if (! is_dir($path)) {
			return [];
		}

		// drie git-aanroepen, waarvan de diff tegen de hoofdlijn de duurste is.
		// Het bord vraagt dit bij elke klik om te weten of er iets op te leveren
		// valt, dus het blijft even staan; wat DevDock zelf verandert haalt het
		// samen met de staat weg
		return $this->remember(
			$this->changedFilesKey($path),
			fn () => $this->readChangedFiles($path),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function readChangedFiles(string $path): array
	{
		$base = $this->branchBase($path);
		$committed = LocalCommand::run($path, ['git', 'diff', '--name-only', $base === null ? 'origin/' . $this->targetBranch($path) . '...HEAD' : $base . '..HEAD']);

		$standing = LocalCommand::run($path, ['git', 'status', '--porcelain=v1']);

		$files = preg_split('/\R/', trim($committed->output())) ?: [];

		// a porcelain line starts with two status columns and a space; trimming
		// the whole block first would eat the leading space of the first line
		foreach (preg_split('/\R/', $standing->output()) ?: [] as $line) {
			$name = trim(substr($line, 3));

			if ($name !== '' && ! str_ends_with($name, self::PROJECT_NAME_FILE)) {
				$files[] = $name;
			}
		}

		return array_values(array_unique(array_filter($files)));
	}

	public function commit(string $path, string $title, string $body, ?array $files = null): ?string
	{
		// the files DevDock writes into a copy have no business in the history of
		// the project, chosen or not; with a choice of files only those go along
		$ownPatterns = [...(array) config('devdock.worktree.own_files'), self::PROJECT_NAME_FILE];
		$ownFiles = array_map(
			fn (string $pattern): string => ':(exclude,glob)' . rtrim($pattern, '/') . (str_ends_with($pattern, '/') ? '/**' : ''),
			$ownPatterns,
		);
		$chosen = $files === null ? ['.'] : array_values(array_filter(
			$files,
			fn (string $file): bool => ! collect($ownPatterns)->contains(fn (string $pattern): bool => str_ends_with($pattern, '/')
				? str_starts_with(str_replace('\\', '/', $file), $pattern)
				: fnmatch($pattern, str_replace('\\', '/', $file))),
		));

		if ($chosen === []) {
			return __('Alleen bestanden van DevDock zelf waren gekozen; die gaan nooit mee in een commit.');
		}

		$staged = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'add', '-A', '--', ...$chosen, ...$ownFiles]);

		if (! $staged->successful()) {
			return $this->reasonOf($staged);
		}

		$command = ['git', 'commit', '-m', $title];

		if (trim($body) !== '') {
			$command[] = '-m';
			$command[] = $body;
		}

		$committed = Process::path($path)
			->timeout(self::TIMEOUT)
			->run($command);

		$this->forgetStatus($path);

		return $committed->successful() ? null : $this->reasonOf($committed);
	}

	/**
	 * What git said when a step failed: its error output, else what it wrote out, such as a hook that refused.
	 */
	private function reasonOf(ProcessResult $processResult): string
	{
		$said = trim($processResult->errorOutput()) ?: trim($processResult->output());

		return $said === '' ? __('Git gaf geen reden.') : mb_substr($said, -1500);
	}

	public function push(string $path): bool
	{
		$status = $this->readStatus($path);
		$branch = $this->getBranch($path);

		if ($branch === null) {
			return false;
		}

		// without an upstream the first push creates the branch of the same
		// name and follows it; never the main line, whatever is set
		$target = $status['upstream'] === null
			? $branch
			: (string) preg_replace('#^origin/#', '', $status['upstream']);

		if (in_array($target, [$this->mainBranch($path), 'master', 'main'], true)) {
			return false;
		}

		$command = match (true) {
			$status['upstream'] === null => ['git', 'push', '-u', 'origin', 'HEAD:refs/heads/' . $target],
			// origin holds commits that are not here, such as the version from before a
			// rebase: overwrite exactly the version seen here, and refuse when origin moved since
			$status['only_on_upstream'] > 0 => ['git', 'push', '--force-with-lease=refs/heads/' . $target . ':' . $this->shaOf($path, $status['upstream']), 'origin', 'HEAD:refs/heads/' . $target],
			default => ['git', 'push', 'origin', 'HEAD:refs/heads/' . $target],
		};

		$pushed = Process::path($path)
			->timeout(self::TIMEOUT)
			->run($command)
			->successful();

		$this->forgetStatus($path);

		return $pushed;
	}

	public function rebase(string $path): ?string
	{
		$target = $this->targetBranch($path);

		$fetched = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'fetch', 'origin', $target]);

		if (! $fetched->successful()) {
			return trim($fetched->errorOutput()) ?: __('Ophalen van origin lukte niet.');
		}

		$before = LocalCommand::run($path, ['git', 'rev-parse', 'HEAD']);

		// what is not committed yet is put aside for the rebase and laid back after it
		$rebased = Process::path($path)->timeout(self::TIMEOUT)->run($this->rebaseCommand($path, $target, setsAsideWork: true));

		$this->forgetStatus($path);

		if (! $rebased->successful()) {
			// a conflict is left for no one: the branch goes back as it was
			Process::path($path)->run(['git', 'rebase', '--abort']);

			return __('Rebasen stuit op conflicten met :main. Er is niets veranderd; laat de agent het oplossen of doe het zelf in Claude Code.', ['main' => $target]);
		}

		if ($before->successful()) {
			$this->rememberRebase($path, trim($before->output()));
		}

		return null;
	}

	public function getLastRebase(string $path): ?RebaseObject
	{
		$file = $path . '/' . self::REBASE_FILE;
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

		return is_array($data) && isset($data['sha_before'], $data['at']) ? RebaseObject::from($data) : null;
	}

	public function beginRebase(string $path): RebaseOutcomeObject
	{
		$target = $this->targetBranch($path);

		// new files count too: what is not committed would have to be set aside for the rebase
		if ($this->lines($path, ['git', 'status', '--porcelain']) !== []) {
			return new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $target, failure: __('Er staat werk dat niet gecommit is. Commit eerst, dan kan de rebase.'));
		}

		$fetched = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'fetch', 'origin', $target]);

		if (! $fetched->successful()) {
			return new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $target, failure: trim($fetched->errorOutput()) ?: __('Ophalen van origin lukte niet.'));
		}

		$before = LocalCommand::run($path, ['git', 'rev-parse', 'HEAD']);
		$rebased = Process::path($path)->timeout(self::TIMEOUT)->run($this->rebaseCommand($path, $target, setsAsideWork: false));

		$this->forgetStatus($path);

		if ($before->successful()) {
			$this->rememberRebase($path, trim($before->output()));
		}

		if ($rebased->successful()) {
			return new RebaseOutcomeObject(RebaseOutcomeEnum::DONE, $target);
		}

		$conflictedFiles = $this->conflictedFilesIn($path);

		if ($conflictedFiles !== [] && $this->isRebasing($path)) {
			return new RebaseOutcomeObject(RebaseOutcomeEnum::CONFLICTED, $target, $conflictedFiles);
		}

		// anything but a conflict is not the agent's to solve: the branch goes back as it was
		Process::path($path)->run(['git', 'rebase', '--abort']);

		return new RebaseOutcomeObject(RebaseOutcomeEnum::FAILED, $target, failure: trim($rebased->errorOutput()) ?: __('Rebasen lukte niet.'));
	}

	public function abortRebase(string $path): bool
	{
		$aborted = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'rebase', '--abort']);

		$this->forgetStatus($path);

		return $aborted->successful();
	}

	public function isRebasing(string $path): bool
	{
		$gitDirectory = $this->gitDirectoryOf($path);

		return $gitDirectory !== null && (is_dir($gitDirectory . '/rebase-merge') || is_dir($gitDirectory . '/rebase-apply'));
	}

	public function getConflictedFiles(string $path): array
	{
		return is_dir($path) ? $this->conflictedFilesIn($path) : [];
	}

	/**
	 * The files git holds as unmerged in a working copy.
	 *
	 * @return list<string>
	 */
	private function conflictedFilesIn(string $path): array
	{
		return $this->lines($path, ['git', 'diff', '--name-only', '--diff-filter=U']);
	}

	/**
	 * The own git folder of a working copy, read from the .git file that points at it.
	 */
	private function gitDirectoryOf(string $path): ?string
	{
		$pointer = $path . '/.git';

		if (is_dir($pointer)) {
			return $pointer;
		}

		if (! is_file($pointer) || preg_match('/^gitdir:\s*(.+)$/m', (string) file_get_contents($pointer), $found) !== 1) {
			return null;
		}

		$gitDirectory = trim($found[1]);

		// a relative pointer counts from the working copy
		return preg_match('#^([A-Za-z]:)?[\\\\/]#', $gitDirectory) === 1 ? $gitDirectory : $path . '/' . $gitDirectory;
	}

	/**
	 * Writes down the commit the branch stood on before a rebase, so that state can always be found back.
	 */
	private function rememberRebase(string $path, string $shaBefore): void
	{
		if (! is_dir($path . '/' . self::DEVDOCK_FOLDER)) {
			@mkdir($path . '/' . self::DEVDOCK_FOLDER, 0775, true);
		}

		file_put_contents($path . '/' . self::REBASE_FILE, (string) json_encode([
			'sha_before' => $shaBefore,
			'at' => CarbonImmutable::now()->toIso8601String(),
		]));
	}

	public function switchToMain(string $repositoryPath, bool $carryChanges = false): ?string
	{
		if ($this->getUncommittedFiles($repositoryPath) !== []) {
			if (! $carryChanges) {
				return __('In het project staat werk dat niet gecommit is. Het blijft op deze branch staan.');
			}

			// the work is set aside in the repository under a label of its branch, so the working copy can lay it back
			$stashed = Process::path($repositoryPath)->run(['git', 'stash', 'push', '--include-untracked', '--message', $this->carriedLabel((string) $this->getBranch($repositoryPath))]);

			if (! $stashed->successful()) {
				return trim($stashed->errorOutput()) ?: __('Het werk in het project kon niet opzij gezet worden.');
			}
		}

		$main = $this->mainBranch($repositoryPath);
		$switched = Process::path($repositoryPath)->timeout(self::TIMEOUT)->run(['git', 'switch', $main]);

		$this->forgetStatus($repositoryPath);

		return $switched->successful() ? null : (trim($switched->errorOutput()) ?: __('Git kon niet terug naar :main.', ['main' => $main]));
	}

	public function getUncommittedFiles(string $repositoryPath): array
	{
		$status = LocalCommand::run($repositoryPath, ['git', 'status', '--porcelain', '--untracked-files=no']);

		if (! $status->successful()) {
			return [];
		}

		return array_values(array_filter(array_map(
			fn (string $line) => trim(substr($line, 3)),
			preg_split('/\R/', rtrim($status->output())) ?: [],
		)));
	}

	public function layBackCarried(string $path): ?string
	{
		$failure = __('Het werk uit het project kwam niet terug in de werkkopie; het staat nog in git stash.');

		// the stash is shared by every checkout of the repository, so the entry is found by its label, never taken from the top
		$entry = $this->stashEntry($path, $this->carriedLabel((string) $this->getBranch($path)));

		if ($entry === null) {
			return $failure;
		}

		$applied = Process::path($path)->run(['git', 'stash', 'apply', $entry['sha']]);

		$this->forgetStatus($path);

		if (! $applied->successful()) {
			return trim($applied->errorOutput()) ?: $failure;
		}

		// found again by its sha: another checkout may have moved it down the stack meanwhile
		$standing = $this->stashEntry($path, null, $entry['sha']);

		if ($standing !== null) {
			Process::path($path)->run(['git', 'stash', 'drop', $standing['ref']]);
		}

		return null;
	}

	private function carriedLabel(string $branch): string
	{
		return 'devdock: carried ' . $branch . ' to a working copy';
	}

	/**
	 * One entry of the stash, found by its message or by its sha.
	 *
	 * @return array{ref: string, sha: string}|null
	 */
	private function stashEntry(string $path, ?string $label, ?string $sha = null): ?array
	{
		$list = LocalCommand::run($path, ['git', 'stash', 'list', '--format=%gd%x09%H%x09%gs']);

		foreach (preg_split('/\R/', trim($list->output())) ?: [] as $line) {
			[$ref, $entrySha, $subject] = array_pad(explode("\t", $line, 3), 3, '');

			if (($label !== null && str_ends_with($subject, $label)) || ($sha !== null && $entrySha === $sha)) {
				return ['ref' => $ref, 'sha' => $entrySha];
			}
		}

		return null;
	}

	public function getUnpushedCommits(string $path): array
	{
		if (! is_dir($path)) {
			return [];
		}

		// kept as long as the status, and thrown away with it after a commit or push
		$output = $this->remember($this->unpushedKey($path), function () use ($path): string {
			$result = LocalCommand::run($path, ['git', 'log', '--format=%h%x09%cI%x09%s', 'HEAD', '--not', '--remotes']);

			return $result->successful() ? trim($result->output()) : '';
		});

		$commits = [];

		foreach (preg_split('/\R/', $output) ?: [] as $line) {
			$parts = explode("\t", $line, 3);

			if (count($parts) === 3) {
				$commits[] = ['sha' => $parts[0], 'at' => CarbonImmutable::parse($parts[1]), 'title' => $parts[2]];
			}
		}

		return $commits;
	}

	public function getRemoteBranches(string $path): array
	{
		$result = LocalCommand::run($path, ['git', 'branch', '-r', '--format=%(refname:short)']);

		if (! $result->successful()) {
			return [];
		}

		$main = $this->mainBranch($path);

		return array_values(array_filter(
			array_map('trim', preg_split('/\R/', trim($result->output())) ?: []),
			fn (string $name) => str_starts_with($name, 'origin/')
				&& ! in_array($name, ['origin', 'origin/HEAD', 'origin/' . $main, 'origin/master', 'origin/main'], true),
		));
	}

	public function setUpstream(string $path, ?string $upstream): bool
	{
		$command = $upstream === null
			? ['git', 'branch', '--unset-upstream']
			: ['git', 'branch', '--set-upstream-to=' . $upstream];

		$result = Process::path($path)->run($command);

		$this->forgetStatus($path);

		// unsetting what was never set is not a failure
		return $result->successful() || $upstream === null;
	}

	public function getBranch(string $path): ?string
	{
		$result = LocalCommand::run($path, ['git', 'symbolic-ref', '--short', 'HEAD']);

		return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : null;
	}

	/**
	 * The full sha a ref points at here; empty when git does not know the ref.
	 */
	private function shaOf(string $path, string $ref): string
	{
		$result = LocalCommand::run($path, ['git', 'rev-parse', '--verify', $ref]);

		return $result->successful() ? trim($result->output()) : '';
	}

	/**
	 * @return list<string>
	 */
	private function lines(string $path, array $command): array
	{
		$result = LocalCommand::run($path, $command);

		if (! $result->successful()) {
			return [];
		}

		return array_values(array_filter(
			explode('
', str_replace('', '', $result->output())),
			fn (string $line) => trim($line) !== '',
		));
	}

	/**
	 * Puts the branch in its own folder, and says whether the branch itself
	 * was created for it.
	 *
	 * @throws WorktreeNotPossibleException
	 */
	private function addToGit(WorktreeObject $worktreeObject): bool
	{
		$takenAt = $this->checkedOutAt($worktreeObject->repositoryPath, $worktreeObject->branch);

		if ($takenAt !== null) {
			throw new WorktreeNotPossibleException(__(
				'De branch :branch staat al uitgecheckt in :path. Git staat één werkkopie per branch toe, dus zet die map eerst op een andere branch.',
				['branch' => $worktreeObject->branch, 'path' => $takenAt],
			));
		}

		$wasLocal = $this->hasRef($worktreeObject->repositoryPath, 'refs/heads/' . $worktreeObject->branch);

		$result = Process::path($worktreeObject->repositoryPath)
			->timeout(self::TIMEOUT)
			->run($this->addCommand($worktreeObject));

		if (! $result->successful()) {
			throw new WorktreeNotPossibleException(trim($result->errorOutput() ?: $result->output()));
		}

		return ! $wasLocal;
	}

	/**
	 * A branch that only exists on the remote is tracked along; one that is
	 * already here is checked out as it is.
	 *
	 * @return list<string>
	 */
	private function addCommand(WorktreeObject $worktreeObject): array
	{
		$isLocal = $this->hasRef($worktreeObject->repositoryPath, 'refs/heads/' . $worktreeObject->branch);

		if ($isLocal) {
			return ['git', 'worktree', 'add', $worktreeObject->path, $worktreeObject->branch];
		}

		$isRemote = $this->hasRef($worktreeObject->repositoryPath, 'refs/remotes/origin/' . $worktreeObject->branch);

		if ($isRemote) {
			return [
				'git',
				'worktree',
				'add',
				'--track',
				'-b',
				$worktreeObject->branch,
				$worktreeObject->path,
				'origin/' . $worktreeObject->branch,
			];
		}

		// a branch that exists nowhere yet is cut from the main line, without
		// tracking it: a push would otherwise go straight to that branch
		return [
			'git',
			'worktree',
			'add',
			'--no-track',
			'-b',
			$worktreeObject->branch,
			$worktreeObject->path,
			'origin/' . $this->mainBranch($worktreeObject->repositoryPath),
		];
	}

	private function hasRef(string $repositoryPath, string $ref): bool
	{
		return LocalCommand::run($repositoryPath, ['git', 'show-ref', '--verify', '--quiet', $ref])
			->successful();
	}

	/**
	 * The branch a new one is cut from, as the remote itself names it.
	 */
	/**
	 * When the branch last moved, from the log git keeps of HEAD. A working
	 * copy points at its own folder inside the checkout with a .git file.
	 */
	private function lastCommitAt(string $path): ?string
	{
		$gitDirectory = $path . '/.git';

		if (is_file($gitDirectory) && preg_match('/^gitdir:\s*(.+)$/m', (string) file_get_contents($gitDirectory), $found) === 1) {
			$gitDirectory = trim($found[1]);

			if (! str_starts_with($gitDirectory, '/') && preg_match('/^[A-Za-z]:/', $gitDirectory) !== 1) {
				$gitDirectory = $path . '/' . $gitDirectory;
			}
		}

		$log = $gitDirectory . '/logs/HEAD';

		return is_file($log) ? CarbonImmutable::createFromTimestamp((int) filemtime($log))->toIso8601String() : null;
	}

	private function mainBranch(string $repositoryPath): string
	{
		// the main line of a remote does not move; asking git every time does
		return Cache::remember('worktree.main.' . md5($repositoryPath), 86400, function () use ($repositoryPath): string {
			$result = LocalCommand::run($repositoryPath, ['git', 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

			$name = trim($result->output());

			return $result->successful() && $name !== ''
				? str_replace('origin/', '', $name)
				: 'master';
		});
	}

	public function setTargetBranch(string $repositoryPath, string $branch, ?string $target): bool
	{
		$key = 'branch.' . $branch . '.' . self::TARGET_KEY;
		// the main line is where a branch goes without a mark, so it is never written down
		$wanted = $target === null || $target === $this->mainBranch($repositoryPath) ? null : $target;
		$current = LocalCommand::run($repositoryPath, ['git', 'config', '--get', $key]);

		if (($current->successful() ? trim($current->output()) : null) === $wanted) {
			return false;
		}

		if ($wanted === null) {
			return LocalCommand::run($repositoryPath, ['git', 'config', '--unset', $key])->successful();
		}

		LocalCommand::run($repositoryPath, ['git', 'config', $key, $wanted]);
		// the comparisons read origin/<target>, which a repository that never fetched it lacks
		Process::path($repositoryPath)->timeout(self::TIMEOUT)->run(['git', 'fetch', '--quiet', 'origin', $wanted]);

		return true;
	}

	/**
	 * The branch the work of a working copy merges into.
	 */
	private function targetBranch(string $path): string
	{
		// a copy that is gone has no branch to ask git about
		$branch = is_dir($path) ? $this->getBranch($path) : null;

		return $branch === null ? $this->mainBranch($path) : $this->targetOf($path, $branch);
	}

	/**
	 * The branch a branch merges into: the target of its pull request, or the main line.
	 */
	private function targetOf(string $repositoryPath, string $branch): string
	{
		$result = LocalCommand::run($repositoryPath, ['git', 'config', '--get', 'branch.' . $branch . '.' . self::TARGET_KEY]);

		return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : $this->mainBranch($repositoryPath);
	}

	/**
	 * The checkout a working copy belongs to. Removing it has to be done from
	 * there, not from inside the folder that is about to disappear.
	 */
	private function repositoryPathOf(string $path): string
	{
		$result = LocalCommand::run($path, ['git', 'rev-parse', '--git-common-dir']);

		if (! $result->successful()) {
			return $path;
		}

		$commonDirectory = trim($result->output());

		if (! str_starts_with($commonDirectory, '/') && ! preg_match('/^[A-Za-z]:/', $commonDirectory)) {
			$commonDirectory = $path . '/' . $commonDirectory;
		}

		return dirname(str_replace(DIRECTORY_SEPARATOR, '/', realpath($commonDirectory) ?: $commonDirectory));
	}

	public function checkedOutAt(string $repositoryPath, string $branch): ?string
	{
		$result = LocalCommand::run($repositoryPath, ['git', 'worktree', 'list', '--porcelain']);

		if (! $result->successful()) {
			return null;
		}

		$path = null;

		foreach (explode("\n", $result->output()) as $line) {
			$line = trim($line);

			if (str_starts_with($line, 'worktree ')) {
				$path = substr($line, 9);
			}

			if ($line === 'branch refs/heads/' . $branch) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * WakaTime names a project after the folder it finds, which for a working
	 * copy is the branch name. This file says which project it really is.
	 */
	private function pinProjectName(string $path, string $project): void
	{
		if (is_dir($path)) {
			@file_put_contents($path . '/' . self::PROJECT_NAME_FILE, $project . "\n");
		}
	}

	private function mapToEntity(WorktreeEloquent $worktreeEloquent): WorktreeEntity
	{
		$path = (string) $worktreeEloquent->path;
		$status = $this->statusOf($path);

		// a working copy stands one folder deep in the checkout it belongs to,
		// which is where its packages are borrowed from
		$borrowedFrom = dirname($path, 2);

		return new WorktreeEntity(
			worktreeId: new WorktreeId(id: $worktreeEloquent->id),
			project: (string) $worktreeEloquent->project,
			branch: (string) $worktreeEloquent->branch,
			branchCreated: (bool) $worktreeEloquent->branch_created,
			path: $path,
			origin: $worktreeEloquent->origin ?? WorktreeOriginEnum::MADE,
			site: $worktreeEloquent->site,
			ownDatabase: $worktreeEloquent->database,
			card: $worktreeEloquent->card,
			changedFiles: $status['changed_files'],
			ahead: $status['ahead'],
			onlyOnUpstream: $status['only_on_upstream'],
			behind: $status['behind'],
			upstream: $status['upstream'],
			mainBranch: $status['main_branch'],
			targetBranch: $status['target_branch'],
			isRebasing: $this->isRebasing($path),
			lastCommitAt: $status['last_commit_at'] === null ? null : CarbonImmutable::parse($status['last_commit_at']),
			dependenciesMissing: is_dir($borrowedFrom . '/vendor') && ! is_dir($path . '/vendor'),
			hasCommits: $status['has_commits'],
			createdAt: $worktreeEloquent->created_at?->toImmutable(),
		);
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, target_branch: string, last_commit_at: ?string}
	 */
	private function statusOf(string $path): array
	{
		if (! is_dir($path)) {
			return $this->emptyStatus();
		}

		// git status over een grote repository kost een vijfde seconde, en het
		// bord vraagt het van elke werkkopie bij elke klik. Een paar seconden
		// onthouden scheelt seconden wachten; wat DevDock zelf verandert haalt
		// de regel meteen weg
		return $this->remember(
			$this->statusKey($path),
			fn () => $this->readStatus($path),
		);
	}

	public function refreshStatuses(int $keepSeconds): void
	{
		$paths = WorktreeEloquent::query()->pluck('path')->map(fn (mixed $path) => (string) $path)->all();

		$this->warmStatuses($paths, $keepSeconds, again: true);

		// what a card's panel reads lies ready too, so opening one never waits on git;
		// reading all of it takes seconds per copy, so only a copy that moved is read again
		$this->readAheadSeconds = self::READ_AHEAD_SECONDS;

		try {
			foreach (array_filter($paths, is_dir(...)) as $path) {
				$signature = $this->readAheadSignature($path);

				if (Cache::get($this->readAheadKey($path)) === $signature) {
					continue;
				}

				foreach (DiffViewEnum::cases() as $diffView) {
					$this->diffBase($path, $diffView);
					$this->getChangesFor($path, $diffView);
				}

				$this->getChangedFiles($path);
				$this->getFingerprint($path);

				Cache::put($this->readAheadKey($path), $signature, self::READ_AHEAD_SECONDS);
			}
		} finally {
			$this->readAheadSeconds = null;
		}
	}

	/**
	 * Everything that changes what the readers would find: where HEAD and the
	 * branch it merges into stand on origin, which files differ, and when those last
	 * changed on disk, since a file that was already changed shows the same
	 * status line after the next edit.
	 */
	private function readAheadSignature(string $path): string
	{
		$status = LocalCommand::run($path, ['git', 'status', '--porcelain=v2', '--branch', '--untracked-files=all']);
		$target = LocalCommand::run($path, ['git', 'rev-parse', '--verify', '--quiet', 'origin/' . $this->targetBranch($path)]);
		$modifiedAt = [];

		foreach (preg_split('/\R/', $status->output()) ?: [] as $line) {
			// the path is the last field of a change line; a rename puts the old one after a tab
			if ($line !== '' && $line[0] !== '#') {
				$file = explode("\t", (string) last(explode(' ', $line)))[0];
				$modifiedAt[] = @filemtime($path . '/' . $file) ?: 0;
			}
		}

		return md5($status->output() . $target->output() . implode(',', $modifiedAt));
	}

	private function readAheadKey(string $path): string
	{
		return 'worktree.read-ahead.' . md5($path);
	}

	/**
	 * What a reader kept for status_seconds, read again while the fetch round
	 * reads ahead.
	 */
	private function remember(string $key, Closure $read): mixed
	{
		if ($this->readAheadSeconds === null) {
			return Cache::remember($key, (int) config('devdock.worktree.status_seconds'), $read);
		}

		$value = $read();

		Cache::put($key, $value, $this->readAheadSeconds);

		return $value;
	}

	public function forgetStatus(string $path): void
	{
		Cache::forget($this->statusKey($path));
		Cache::forget($this->changedFilesKey($path));
		Cache::forget($this->changesKey($path));
		Cache::forget($this->branchChangesKey($path));
		Cache::forget($this->unpushedKey($path));
		Cache::forget('worktree.unpushed-changes.' . md5($path));
		Cache::forget('worktree.fingerprint.' . md5($path));
		Cache::forget($this->diffBaseKey($path, DiffViewEnum::UNPUSHED));
		Cache::forget($this->diffBaseKey($path, DiffViewEnum::BRANCH));
		Cache::forget($this->readAheadKey($path));
	}

	private function unpushedKey(string $path): string
	{
		return 'worktree.unpushed.' . md5($path);
	}

	private function statusKey(string $path): string
	{
		return 'worktree.status.' . md5($path);
	}

	private function changedFilesKey(string $path): string
	{
		return 'worktree.changed.' . md5($path);
	}

	private function branchChangesKey(string $path): string
	{
		return 'worktree.branch-changes.' . md5($path);
	}

	private function changesKey(string $path): string
	{
		return 'worktree.changes.' . md5($path);
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, target_branch: string, last_commit_at: ?string}
	 */
	private function emptyStatus(): array
	{
		return [
			'changed_files' => 0,
			'ahead' => 0,
			'only_on_upstream' => 0,
			'behind' => 0,
			'has_commits' => false,
			'upstream' => null,
			'main_branch' => 'master',
			'target_branch' => 'master',
			'last_commit_at' => null,
		];
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, target_branch: string, last_commit_at: ?string}
	 */
	private function readStatus(string $path): array
	{
		$target = $this->targetBranch($path);

		return $this->parseStatus(
			$path,
			$target,
			LocalCommand::run($path, $this->statusCommand()),
			LocalCommand::run($path, $this->sidesCommand($target)),
		);
	}

	/**
	 * Reads the state of every working copy that is not remembered yet, all
	 * at the same time. One after the other, a board with eight copies of a
	 * large repository waits seconds on git.
	 *
	 * @param  array<int, string>  $paths
	 */
	private function warmStatuses(array $paths, ?int $keepSeconds = null, bool $again = false): void
	{
		$cold = array_values(array_filter(
			$paths,
			fn (string $path) => is_dir($path) && ($again || ! Cache::has($this->statusKey($path))),
		));

		// a single copy is read on its own when it is asked for; refreshing does them all
		if (count($cold) < ($again ? 1 : 2)) {
			return;
		}

		$targets = array_map(fn (string $path) => $this->targetBranch($path), $cold);

		$results = Process::pool(function (Pool $pool) use ($cold, $targets): void {
			foreach ($cold as $index => $path) {
				$pool->as('status-' . $index)->path($path)->command($this->statusCommand());
				$pool->as('sides-' . $index)->path($path)->command($this->sidesCommand($targets[$index]));
				$pool->as('unpushed-' . $index)->path($path)->command(['git', 'log', '--format=%h%x09%cI%x09%s', 'HEAD', '--not', '--remotes']);
			}
		})->start()->wait();

		foreach ($cold as $index => $path) {
			$seconds = $keepSeconds ?? (int) config('devdock.worktree.status_seconds');
			$unpushed = $results['unpushed-' . $index];

			Cache::put(
				$this->statusKey($path),
				$this->parseStatus($path, $targets[$index], $results['status-' . $index], $results['sides-' . $index]),
				$seconds,
			);
			Cache::put($this->unpushedKey($path), $unpushed->successful() ? trim($unpushed->output()) : '', $seconds);
		}
	}

	/**
	 * @return list<string>
	 */
	private function statusCommand(): array
	{
		return ['git', 'status', '--porcelain=v2', '--branch'];
	}

	/**
	 * One call for both sides against the branch this one merges into: what
	 * came in there, and what this branch has that it does not.
	 *
	 * @return list<string>
	 */
	private function sidesCommand(string $target): array
	{
		return ['git', 'rev-list', '--left-right', '--count', 'origin/' . $target . '...HEAD'];
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, target_branch: string, last_commit_at: ?string}
	 */
	private function parseStatus(string $path, string $target, ProcessResult $result, ProcessResult $sides): array
	{
		if (! $result->successful()) {
			return $this->emptyStatus();
		}

		$changedFiles = 0;
		$upstream = null;
		$aheadOfUpstream = 0;
		$onlyOnUpstream = 0;

		foreach (explode("\n", $result->output()) as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#')) {
				if (str_starts_with($line, '# branch.upstream ')) {
					$upstream = substr($line, 18);
				}

				if (str_starts_with($line, '# branch.ab ') && preg_match('/\+(\d+) -(\d+)/', $line, $counts) === 1) {
					$aheadOfUpstream = (int) $counts[1];
					$onlyOnUpstream = (int) $counts[2];
				}

				continue;
			}

			// the pinned project name is ours, not work someone did here
			if (str_ends_with($line, self::PROJECT_NAME_FILE)) {
				continue;
			}

			$changedFiles++;
		}

		[$behind, $aheadOfMain] = array_map('intval', preg_split('/\s+/', trim($sides->output())) ?: [0, 0]) + [0, 0];
		$lastCommit = $this->lastCommitAt($path);

		return [
			'changed_files' => $changedFiles,

			// a branch cut without tracking has no upstream to count against; all
			// it has beyond the main line is then not pushed. After a rebase the
			// upstream still stands on the old main line, and everything that came
			// in there since counts as ahead of it, so only the own commits count
			'ahead' => match (true) {
				$upstream === null => $aheadOfMain,
				$onlyOnUpstream > 0 && $sides->successful() => min($aheadOfUpstream, $aheadOfMain),
				default => $aheadOfUpstream,
			},
			'only_on_upstream' => $upstream === null ? 0 : $onlyOnUpstream,
			'behind' => $sides->successful() ? $behind : 0,
			'has_commits' => $sides->successful() && $aheadOfMain > 0,
			'upstream' => $upstream,
			'main_branch' => $this->mainBranch($path),
			'target_branch' => $target,
			'last_commit_at' => $lastCommit === '' ? null : $lastCommit,
		];
	}
}
