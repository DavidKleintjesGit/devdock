<?php

declare(strict_types=1);

namespace Src\Worktree\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Src\Worktree\Contracts\WorktreeRepositoryContract;
use Src\Worktree\Exceptions\WorktreeNotPossibleException;
use Src\Worktree\Models\Worktree as WorktreeEloquent;
use Src\Worktree\Objects\ChangeObject;
use Src\Worktree\Objects\ChangeObjectCollection;
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

	public function create(WorktreeObject $worktreeObject): WorktreeEntity
	{
		if (! is_dir($worktreeObject->path)) {
			$this->addToGit($worktreeObject);
		}

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
			],
		);

		return $this->mapToEntity($worktreeEloquent);
	}

	public function pinSite(WorktreeId $worktreeId, ?string $site): WorktreeEntity
	{
		$worktreeEloquent = WorktreeEloquent::query()->findOrFail($worktreeId->id);

		$worktreeEloquent->update(['site' => $site]);

		return $this->mapToEntity($worktreeEloquent->fresh());
	}

	public function getAll(): WorktreeEntityCollection
	{
		$worktreeEntities = WorktreeEloquent::query()
			->orderBy('project')
			->orderBy('branch')
			->get()
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

		if (is_dir($path)) {
			// the pinned project name is ours; without taking it back git sees an
			// unknown file and refuses, while real work should still stop us
			@unlink($path . '/' . self::PROJECT_NAME_FILE);

			$removed = Process::path($this->repositoryPathOf($path))
				->timeout(self::TIMEOUT)
				->run(['git', 'worktree', 'remove', $path])
				->successful();

			if (! $removed) {
				$this->pinProjectName($path, (string) $worktreeEloquent->project);

				return false;
			}
		}

		$worktreeEloquent->delete();

		return true;
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
			->run(['git', 'diff', 'origin/' . $this->mainBranch($path), '--', $file]);

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

		$lines = preg_split('/\R/', (string) file_get_contents($whole)) ?: [];

		return implode("\n", [
			'--- /dev/null',
			'+++ b/' . $file,
			...array_map(fn (string $line) => '+' . $line, $lines),
		]);
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

	public function commit(string $path, string $title, string $body): bool
	{
		// the pinned project name is ours and has no business in his history
		$staged = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'add', '-A', '--', '.', ':(exclude)' . self::PROJECT_NAME_FILE])
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
		$pushed = Process::path($path)
			->timeout(self::TIMEOUT)
			->run(['git', 'push'])
			->successful();

		$this->forgetStatus($path);

		return $pushed;
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
	private function lines(string $path, string $command): array
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
	 * @throws WorktreeNotPossibleException
	 */
	private function addToGit(WorktreeObject $worktreeObject): void
	{
		$takenAt = $this->checkedOutAt($worktreeObject->repositoryPath, $worktreeObject->branch);

		if ($takenAt !== null) {
			throw new WorktreeNotPossibleException(__(
				'De branch :branch staat al uitgecheckt in :path. Git staat één werkkopie per branch toe, dus zet die map eerst op een andere branch.',
				['branch' => $worktreeObject->branch, 'path' => $takenAt],
			));
		}

		$result = Process::path($worktreeObject->repositoryPath)
			->timeout(self::TIMEOUT)
			->run($this->addCommand($worktreeObject));

		if (! $result->successful()) {
			throw new WorktreeNotPossibleException(trim($result->errorOutput() ?: $result->output()));
		}
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
	private function mainBranch(string $repositoryPath): string
	{
		$result = Process::path($repositoryPath)
			->run(['git', 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

		$name = trim($result->output());

		return $result->successful() && $name !== ''
			? str_replace('origin/', '', $name)
			: 'master';
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
			path: $path,
			site: $worktreeEloquent->site,
			card: $worktreeEloquent->card,
			changedFiles: $status['changed_files'],
			ahead: $status['ahead'],
			dependenciesMissing: is_dir($borrowedFrom . '/vendor') && ! is_dir($path . '/vendor'),
			hasCommits: $this->hasCommits($path),
			createdAt: $worktreeEloquent->created_at?->toImmutable(),
		);
	}

	/**
	 * @return array{changed_files: int, ahead: int}
	 */
	private function statusOf(string $path): array
	{
		if (! is_dir($path)) {
			return ['changed_files' => 0, 'ahead' => 0];
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

	public function forgetStatus(string $path): void
	{
		Cache::forget($this->statusKey($path));
		Cache::forget($this->changedFilesKey($path));
		Cache::forget($this->changesKey($path));
		Cache::forget('worktree.commits.' . md5($path));
	}

	/**
	 * Of er op deze branch al een commit staat die nog niet in de hoofdlijn
	 * zit. Dat is het moment waarop werk opgeleverd is: pas dan valt er iets
	 * te beoordelen.
	 */
	private function hasCommits(string $path): bool
	{
		if (! is_dir($path)) {
			return false;
		}

		return Cache::remember(
			'worktree.commits.' . md5($path),
			(int) config('devdock.worktree.status_seconds'),
			function () use ($path) {
				$result = Process::path($path)->run([
					'git',
					'rev-list',
					'--count',
					'origin/' . $this->mainBranch($path) . '..HEAD',
				]);

				return $result->successful() && (int) trim($result->output()) > 0;
			},
		);
	}

	private function statusKey(string $path): string
	{
		return 'worktree.status.' . md5($path);
	}

	private function changedFilesKey(string $path): string
	{
		return 'worktree.changed.' . md5($path);
	}

	private function changesKey(string $path): string
	{
		return 'worktree.changes.' . md5($path);
	}

	/**
	 * @return array{changed_files: int, ahead: int}
	 */
	private function readStatus(string $path): array
	{
		$result = Process::path($path)->run('git status --porcelain=v2 --branch');

		if (! $result->successful()) {
			return ['changed_files' => 0, 'ahead' => 0];
		}

		$changedFiles = 0;
		$ahead = 0;

		foreach (explode("\n", $result->output()) as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#')) {
				if (str_starts_with($line, '# branch.ab ') && preg_match('/\+(\d+)/', $line, $counts)) {
					$ahead = (int) $counts[1];
				}

				continue;
			}

			// the pinned project name is ours, not work someone did here
			if (str_ends_with($line, self::PROJECT_NAME_FILE)) {
				continue;
			}

			$changedFiles++;
		}

		return ['changed_files' => $changedFiles, 'ahead' => $ahead];
	}
}
