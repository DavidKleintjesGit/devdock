<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Enums\WorktreeOriginEnum;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Models\Worktree as WorktreeEloquent;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\ChangeObjectCollection;
use Src\Worktree\Objects\RebaseObject;
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

	public function adopt(string $project, string $branch, string $path, WorktreeOriginEnum $origin, ?string $card): WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()->updateOrCreate(
			['project' => $project, 'branch' => $branch],
			['path' => $path, 'card' => $card, 'origin' => $origin, 'branch_created' => false],
		);

		return $this->mapToEntity($worktreeEloquent);
	}

	public function getPlaces(string $repositoryPath): array
	{
		$result = Process::path($repositoryPath)->run(['git', 'worktree', 'list', '--porcelain']);

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
		return Process::path($repositoryPath)
			->run(['git', 'merge-base', '--is-ancestor', 'refs/heads/' . $branch, 'origin/' . $this->mainBranch($repositoryPath)])
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

		$rows = Cache::remember(
			$this->branchChangesKey($path),
			(int) config('devdock.worktree.status_seconds'),
			fn () => $this->readBranchChanges($path),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	/**
	 * Against the commit where the branch left the main line, and including
	 * what is not committed yet: what the pull request will show.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function readBranchChanges(string $path): array
	{
		$base = $this->branchBase($path);

		if ($base === null) {
			return [];
		}

		$kinds = [];

		foreach ($this->lines($path, ['git', 'diff', '--name-status', '--find-renames', $base]) as $line) {
			$parts = explode("\t", $line);
			$kinds[end($parts)] = $parts[0];
		}

		$rows = [];

		foreach ($this->lines($path, ['git', 'diff', '--numstat', '--find-renames', $base]) as $line) {
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
	 * The commit where this branch left the main line on origin.
	 */
	private function branchBase(string $path): ?string
	{
		$result = Process::path($path)->run(['git', 'merge-base', 'origin/' . $this->mainBranch($path), 'HEAD']);

		return $result->successful() ? (trim($result->output()) ?: null) : null;
	}

	public function getChanges(string $path): ChangeObjectCollection
	{
		if (! is_dir($path)) {
			return ChangeObjectCollection::fromArray([]);
		}

		// alleen de gegevens gaan de cache in: objecten overleven het opslaan
		// niet en komen er half terug
		$rows = Cache::remember(
			$this->changesKey($path),
			(int) config('devdock.worktree.status_seconds'),
			fn () => $this->readChanges($path),
		);

		return ChangeObjectCollection::fromArray(array_map(
			fn (array $row) => ChangeObject::from($row),
			$rows,
		));
	}

	/**
	 * Elke wijziging met hoeveel er verschoof: twee git-aanroepen, en de
	 * Worktree-tab vraagt ze bij elke klik opnieuw.
	 */
	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function readChanges(string $path): array
	{
		$counts = $this->lineCounts($path);
		$rows = [];

		foreach ($this->lines($path, 'git status --porcelain') as $line) {
			$status = trim(substr($line, 0, 2));
			$file = trim(substr($line, 2));

			if ($file === '' || $file === self::PROJECT_NAME_FILE) {
				continue;
			}

			$rows[] = [
				'path' => $file,
				'status' => $status,
				'added' => $counts[$file]['added'] ?? 0,
				'removed' => $counts[$file]['removed'] ?? 0,
			];
		}

		return $rows;
	}

	public function getDiff(string $path, string $file): ?string
	{
		if (! is_dir($path) || $file === '') {
			return null;
		}

		// tegen de hoofdlijn en niet tegen HEAD: dan staat er wat deze branch
		// aan dit bestand deed, of het nu al gecommit is of niet
		$result = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'diff', $this->branchBase($path) ?? 'origin/' . $this->mainBranch($path), '--', $file]);

		$patch = trim($result->output());

		if ($patch !== '') {
			return $patch;
		}

		// git kent dit bestand nog niet, dus er is niets om tegen af te zetten
		return $this->newFile($path, $file);
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

		return Cache::remember(
			'worktree.fingerprint.' . md5($path),
			(int) config('devdock.worktree.status_seconds'),
			function () use ($path) {
				$main = $this->mainBranch($path);

				// against the main line, so a commit of the judged work does not
				// count as a change; files git never saw are taken in by content
				$diff = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'diff', 'origin/' . $main]);
				$untracked = Process::path($path)->run(['git', 'ls-files', '--others', '--exclude-standard']);

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

		$result = Process::path($path)->run(['git', 'rev-parse', '--short', 'HEAD']);

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
		return Cache::remember(
			$this->changedFilesKey($path),
			(int) config('devdock.worktree.status_seconds'),
			fn () => $this->readChangedFiles($path),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function readChangedFiles(string $path): array
	{
		$main = $this->mainBranch($path);

		$committed = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'diff', '--name-only', 'origin/' . $main . '...HEAD']);

		$standing = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'status', '--porcelain=v1']);

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

	public function commit(string $path, string $title, string $body, ?array $files = null): bool
	{
		// the pinned project name is ours and has no business in his history;
		// with a choice of files only those go along, the rest stays standing
		$staged = Process::path($path)
			->timeout(self::TIMEOUT)
			->run($files === null
				? ['git', 'add', '-A', '--', '.', ':(exclude)' . self::PROJECT_NAME_FILE]
				: ['git', 'add', '-A', '--', ...$files])
			->successful();

		if (! $staged) {
			return false;
		}

		$command = ['git', 'commit', '-m', $title];

		if (trim($body) !== '') {
			$command[] = '-m';
			$command[] = $body;
		}

		$committed = Process::path($path)
			->timeout(self::TIMEOUT)
			->run($command)
			->successful();

		$this->forgetStatus($path);

		return $committed;
	}

	public function push(string $path): bool
	{
		$status = $this->readStatus($path);
		$branch = $this->currentBranch($path);

		if ($branch === null) {
			return false;
		}

		// without an upstream the first push creates the branch of the same
		// name and follows it; never the main line, whatever is set
		$target = $status['upstream'] === null
			? $branch
			: (string) preg_replace('#^origin/#', '', $status['upstream']);

		if (in_array($target, [$status['main_branch'], 'master', 'main'], true)) {
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
		$main = $this->mainBranch($path);

		$fetched = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'fetch', 'origin', $main]);

		if (! $fetched->successful()) {
			return trim($fetched->errorOutput()) ?: __('Ophalen van origin lukte niet.');
		}

		$before = Process::path($path)->run(['git', 'rev-parse', 'HEAD']);

		// what is not committed yet is put aside for the rebase and laid back after it
		$rebased = Process::path($path)->timeout(self::TIMEOUT)->run(['git', 'rebase', '--autostash', 'origin/' . $main]);

		$this->forgetStatus($path);

		if (! $rebased->successful()) {
			// a conflict is left for no one: the branch goes back as it was
			Process::path($path)->run(['git', 'rebase', '--abort']);

			return __('Rebasen stuit op conflicten met :main. Er is niets veranderd; laat de agent het oplossen of doe het zelf in Claude Code.', ['main' => $main]);
		}

		if ($before->successful()) {
			if (! is_dir($path . '/' . self::DEVDOCK_FOLDER)) {
				@mkdir($path . '/' . self::DEVDOCK_FOLDER, 0775, true);
			}

			file_put_contents($path . '/' . self::REBASE_FILE, (string) json_encode([
				'sha_before' => trim($before->output()),
				'at' => CarbonImmutable::now()->toIso8601String(),
			]));
		}

		return null;
	}

	public function getLastRebase(string $path): ?RebaseObject
	{
		$file = $path . '/' . self::REBASE_FILE;
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

		return is_array($data) && isset($data['sha_before'], $data['at']) ? RebaseObject::from($data) : null;
	}

	public function switchToMain(string $repositoryPath, bool $carryChanges = false): ?string
	{
		if ($this->getUncommittedFiles($repositoryPath) !== []) {
			if (! $carryChanges) {
				return __('In het project staat werk dat niet gecommit is. Het blijft op deze branch staan.');
			}

			// the work is set aside in the repository, so the working copy can lay it back
			$stashed = Process::path($repositoryPath)->run(['git', 'stash', 'push', '--include-untracked', '--message', 'devdock: carried to a working copy']);

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
		$status = Process::path($repositoryPath)->run(['git', 'status', '--porcelain', '--untracked-files=no']);

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
		$popped = Process::path($path)->run(['git', 'stash', 'pop']);

		$this->forgetStatus($path);

		return $popped->successful() ? null : (trim($popped->errorOutput()) ?: __('Het werk uit het project kwam niet terug in de werkkopie; het staat nog in git stash.'));
	}

	public function getUnpushedCommits(string $path): array
	{
		if (! is_dir($path)) {
			return [];
		}

		// kept as long as the status, and thrown away with it after a commit or push
		$output = Cache::remember($this->unpushedKey($path), (int) config('devdock.worktree.status_seconds'), function () use ($path): string {
			$result = Process::path($path)->run(['git', 'log', '--format=%h%x09%cI%x09%s', 'HEAD', '--not', '--remotes']);

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
		$result = Process::path($path)->run(['git', 'branch', '-r', '--format=%(refname:short)']);

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

	private function currentBranch(string $path): ?string
	{
		$result = Process::path($path)->run(['git', 'symbolic-ref', '--short', 'HEAD']);

		return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : null;
	}

	/**
	 * The full sha a ref points at here; empty when git does not know the ref.
	 */
	private function shaOf(string $path, string $ref): string
	{
		$result = Process::path($path)->run(['git', 'rev-parse', '--verify', $ref]);

		return $result->successful() ? trim($result->output()) : '';
	}

	/**
	 * How many lines moved per file. A file git has not seen before is not in
	 * a diff at all, so it has no counts.
	 *
	 * @return array<string, array{added: int, removed: int}>
	 */
	private function lineCounts(string $path): array
	{
		$counts = [];

		foreach ($this->lines($path, 'git diff --numstat HEAD') as $line) {
			$parts = preg_split('/	/', $line);

			if ($parts === false || count($parts) < 3) {
				continue;
			}

			$counts[$parts[2]] = ['added' => (int) $parts[0], 'removed' => (int) $parts[1]];
		}

		return $counts;
	}

	/**
	 * @return list<string>
	 */
	private function lines(string $path, string|array $command): array
	{
		$result = Process::path($path)->run($command);

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
		return Process::path($repositoryPath)
			->run(['git', 'show-ref', '--verify', '--quiet', $ref])
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
			$result = Process::path($repositoryPath)
				->run(['git', 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

			$name = trim($result->output());

			return $result->successful() && $name !== ''
				? str_replace('origin/', '', $name)
				: 'master';
		});
	}

	/**
	 * The checkout a working copy belongs to. Removing it has to be done from
	 * there, not from inside the folder that is about to disappear.
	 */
	private function repositoryPathOf(string $path): string
	{
		$result = Process::path($path)->run(['git', 'rev-parse', '--git-common-dir']);

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
		$result = Process::path($repositoryPath)->run(['git', 'worktree', 'list', '--porcelain']);

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
			lastCommitAt: $status['last_commit_at'] === null ? null : CarbonImmutable::parse($status['last_commit_at']),
			dependenciesMissing: is_dir($borrowedFrom . '/vendor') && ! is_dir($path . '/vendor'),
			hasCommits: $status['has_commits'],
			createdAt: $worktreeEloquent->created_at?->toImmutable(),
		);
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, last_commit_at: ?string}
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
		return Cache::remember(
			$this->statusKey($path),
			(int) config('devdock.worktree.status_seconds'),
			fn () => $this->readStatus($path),
		);
	}

	public function refreshStatuses(int $keepSeconds): void
	{
		$this->warmStatuses(
			WorktreeEloquent::query()->pluck('path')->map(fn (mixed $path) => (string) $path)->all(),
			$keepSeconds,
			again: true,
		);
	}

	public function forgetStatus(string $path): void
	{
		Cache::forget($this->statusKey($path));
		Cache::forget($this->changedFilesKey($path));
		Cache::forget($this->changesKey($path));
		Cache::forget($this->branchChangesKey($path));
		Cache::forget($this->unpushedKey($path));
		Cache::forget('worktree.fingerprint.' . md5($path));
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
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, last_commit_at: ?string}
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
			'last_commit_at' => null,
		];
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, last_commit_at: ?string}
	 */
	private function readStatus(string $path): array
	{
		$main = $this->mainBranch($path);

		return $this->parseStatus(
			$path,
			$main,
			Process::path($path)->run($this->statusCommand()),
			Process::path($path)->run($this->sidesCommand($main)),
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

		$mains = array_map(fn (string $path) => $this->mainBranch($path), $cold);

		$results = Process::pool(function (Pool $pool) use ($cold, $mains): void {
			foreach ($cold as $index => $path) {
				$pool->as('status-' . $index)->path($path)->command($this->statusCommand());
				$pool->as('sides-' . $index)->path($path)->command($this->sidesCommand($mains[$index]));
				$pool->as('unpushed-' . $index)->path($path)->command(['git', 'log', '--format=%h%x09%cI%x09%s', 'HEAD', '--not', '--remotes']);
			}
		})->start()->wait();

		foreach ($cold as $index => $path) {
			$seconds = $keepSeconds ?? (int) config('devdock.worktree.status_seconds');
			$unpushed = $results['unpushed-' . $index];

			Cache::put(
				$this->statusKey($path),
				$this->parseStatus($path, $mains[$index], $results['status-' . $index], $results['sides-' . $index]),
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
	 * One call for both sides against the main line: what came in there, and
	 * what this branch has that the main line does not.
	 *
	 * @return list<string>
	 */
	private function sidesCommand(string $main): array
	{
		return ['git', 'rev-list', '--left-right', '--count', 'origin/' . $main . '...HEAD'];
	}

	/**
	 * @return array{changed_files: int, ahead: int, only_on_upstream: int, behind: int, has_commits: bool, upstream: ?string, main_branch: string, last_commit_at: ?string}
	 */
	private function parseStatus(string $path, string $main, ProcessResult $result, ProcessResult $sides): array
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
			'main_branch' => $main,
			'last_commit_at' => $lastCommit === '' ? null : $lastCommit,
		];
	}
}
